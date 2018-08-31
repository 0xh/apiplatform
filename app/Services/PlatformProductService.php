<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/8/30
 * Time: 10:34
 */

namespace App\Services;


use App\Models\PlatformProduct;
use App\Models\PlatformProductCategory;

class PlatformProductService extends BaseService
{
    /*
     *  產品列表獲取
     */
    public function productList($type = 'default'): Array
    {
        $paginte = config('platformProduct.paginte');

        return ( new PlatformProduct())->getList($paginte, $type);
    }

    /*
     *  分类获取
     */
    public function getCategoriesWithProduct(): Array
    {
        // has cache
//        cache()
        if(cache('categoriesWithProduct')){
            return cache('categoriesWithProduct');
        }else{
            $lists = $this->getCategoriesWithProductFromDb();

            cache(['categoriesWithProduct' => $lists], config('cache.categories_with_product'));

            return $lists;
        }
    }

    protected function getCategoriesWithProductFromDb(): Array
    {
        $cate_lists = PlatformProductCategory::with('products:id,name,detail,category_id')->get(['id','name','detail','parent_id'])->toArray();

        // sort
        return $this->treeSort($cate_lists);
    }
}