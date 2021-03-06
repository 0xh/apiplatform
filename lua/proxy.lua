-- require tool
local tool = require('resty.tool')
local zhttp = require("resty.http")
local redis_client = require('resty.redis_client')
local redis = redis_client:new()

--local json = require "cjson";
--local string = require('resty.string')

-- body
ngx.req.read_body();

-- set header
-- http handle
local capture_headers = {}
function httpHandler()
    local re_headers = ngx.req.get_headers()
    for key, val in pairs(re_headers) do
        --        ngx.say(key)
        capture_headers[key] = val
        ngx.req.set_header(key, val);
    end
end

-- gzip handle
ngx.req.set_header('Accept-Encoding', 'default')
capture_headers['Accept-Encoding'] = 'default'
-- http error deal
function httpErrorHandler(err)
    --    print("Http headers deal error:", err)
    tool.respClient(4070, 'http头信息格式不正确')
end

status = xpcall(httpHandler, httpErrorHandler)

local request_uri = ngx.var.uri
local request_method = ngx.var.request_method
local request_args = ngx.req.get_uri_args() -- table

-- add real ip
capture_headers['X-Forwarded-For'] = ngx.var.remote_addr
-- http module
local httpc = zhttp.new()

-- post args handle
local post_args = ngx.req.get_post_args()
local post_str = ''
for k, v in pairs(post_args) do
    post_str = post_str .. k .. '=' .. v .. '&'
end

post_str = string.sub(post_str, 1, string.len(post_str) - 1)

-- 获取 列表  做 负载
local node_list = redis:exec(function(red) return red:SMEMBERS('node_load_balancing') end)
--ngx.print('table_len:' .. table.getn(node_list))

if next(node_list) == nil then
    return tool.respClient(5123, '服务提供已关闭，请稍后再试！')
end

math.randomseed(tostring(os.time()):reverse():sub(1, 6))
local index = math.random(1, table.getn(node_list))
local apiplatform_service_base_uri = node_list[index]
--ngx.print(apiplatform_service_base_uri)
--local apiplatform_service_base_uri = redis:exec(function(red) return red:get('apiplatform_service_base_uri') end)
if apiplatform_service_base_uri == ngx.null or apiplatform_service_base_uri == nil or err then
    tool.respClient(5123, '服务提供已关闭，请稍后再试！')
end

local request_base_uri = 'http://' .. apiplatform_service_base_uri

-- get services mapping
local route_map_table = redis:exec(function(red)
    local info = red:HGETALL('services_map:' .. request_uri)
    local rt = {}

    for i = 1, #info, 2 do
        rt[info[i]] = info[i + 1]
    end
    --            ngx.print(cjson.encode(rt))
    return rt
end)

if next(route_map_table) == nil or route_map_table['internal_api_path'] == nil then
    return tool.respClient(4033, '未发现对应服务')
end

-- request method validate
if (request_method ~= route_map_table['request_method']) then
    return tool.respClient(4034, '请求方式不正确')
end

local timeout = timeout or 5000
httpc:set_timeout(timeout)
local body = ngx.req.read_body();
local res, err_ = httpc:request_uri(request_base_uri, {
    path = route_map_table['internal_api_path'],
    method = route_map_table['internal_request_method'],
    body = body,
    query = request_args,
    headers = capture_headers,
    keepalive_timeout = 60,
    keepalive_pool = 100
})

--local cjson = require "cjson";
-- error handle
if not res then
    ngx.log(ngx.CRIT, 'http request service error:' .. err_)
    tool.respClient(5103, '服务异常' .. err_)
else
    ngx.print(res.body)
    -- response header handle ?
    -- ngx.print(cjson.encode(res.headers))
    -- real http status handle ?
    -- ngx.print(res.status)
end
