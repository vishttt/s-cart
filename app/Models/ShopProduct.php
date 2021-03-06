<?php

namespace App\Models;

use App\Models\Config;
use App\Models\ShopOption;
use App\Models\ShopOptionDetail;
use App\Models\ShopSpecialPrice;
use Illuminate\Database\Eloquent\Model;
use Scart;

class ShopProduct extends Model
{
    public $table = 'shop_product';

    public function brand()
    {
        return $this->belongsTo('App\Models\ShopBrand', 'brand_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo('App\Models\ShopCategory', 'category_id', 'id');
    }

    public function images()
    {
        return $this->hasMany('App\Models\ShopImage', 'product_id', 'id');
    }
    public function likes()
    {
        return $this->hasMany('App\Models\ShopProductLike', 'product_id', 'id');
    }
    public function options()
    {
        return $this->hasMany('App\Models\ShopOptionDetail', 'product_id', 'id');
    }
    public function special_price()
    {
        return $this->hasMany('App\Models\ShopSpecialPrice', 'product_id', 'id');
    }
/**
 * [getPrice description]
 * @param  [type] $id      [description]
 * @param  [type] $opt_sku [description]
 * @return [type]          [description]
 */
    public function getPrice($id = null, $opt_sku = null)
    {
        $id      = ($id == null) ? $this->id : $id;
        $special = ShopSpecialPrice::where('product_id', $id)
            ->where('status', 1)
            ->where(function ($query) {
                $query->where('date_end', '>=', date("Y-m-d"))
                    ->orWhereNull('date_end');
            })
            ->where(function ($query) {
                $query->where('date_start', '<=', date("Y-m-d"))
                    ->orWhereNull('date_start');
            })
            ->first();
        if ($special) {
            return $special->price;
        } else {
            return $this->find($id)->price;
        }
    }

    /**
     * [showPrice description]
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public function showPrice()
    {

        if ($this->price == $this->getPrice()) {
            return '<div class="price-row"><span class="price">' . number_format($this->price) . '</span></div>';
        } else {
            return '<div class="price-row"><span class="price">' . number_format($this->getPrice()) . '</span><span class="price-old">' . $this->price . '</span></div>';
        }
    }
    /**
     * [getProducts description]
     * @param  [type] $type  [description]
     * @param  [type] $limit [description]
     * @param  [type] $opt   [description]
     * @return [type]        [description]
     */
    public function getProducts($type = null, $limit = null, $opt = null)
    {
        $query = ShopProduct::where('status', 1);
        if ($type) {
            $query = $query->where('type', $type);
        }

        //Hidden product out of stock
        if ((int) Config::select('value')->where('key', 'product_display_out_of_stock')->first()->value == 0) {
            $query = $query->where('stock', '>', 0);
        }

        if (!(int) $limit) {
            return $query->get();
        } else
        if ($opt == 'paginate') {
            return $query->paginate((int) $limit);
        } else
        if ($opt == 'random') {
            return $query->inRandomOrder()->limit($limit)->get();
        } else {
            return $query->orderBy('id', 'desc')->limit($limit)->get();
        }
    }

    public static function getSearch($keyword)
    {
        return self::where('status', 1)
            ->where(function ($sql) use ($keyword) {
                $sql->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('sku', 'like', '%' . $keyword . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate(12);
    }

    public function getProductsSpecial($limit = null, $random = true)
    {

        $special = (new ShopSpecialPrice)
            ->where('status', 1)
            ->where(function ($query) {
                $query->where('date_end', '>=', date("Y-m-d"))
                    ->orWhereNull('date_end');
            })
            ->where(function ($query) {
                $query->where('date_start', '<=', date("Y-m-d"))
                    ->orWhereNull('date_start');
            })
        ;
        if ($random) {
            $special = $special->inRandomOrder();
        }
        if ($limit) {
            $special = $special->limit($limit);
        }
        return $special->get();
    }

    public function addLike($pId, $uId)
    {
        $check = $this->checkCanLike($pId, $uId);
        if ($check === 2) {
            return $this->likes()->insert(['product_id' => $pId, 'users_id' => $uId]);
        } else {
            return false;
        }
    }

    public function checkCanLike($pId, $uId)
    {
        if (empty($pId) || empty($uId)) {
            return 0; // no exist
        } else {
            $check = $this->likes()->where('product_id', $pId)->where('users_id', $uId)->first();
            if ($check) {
                return 1; // liked
            } else {
                return 2; // can like
            }
        }

    }

    protected static function boot()
    {
        parent::boot();
        // before delete() method call this
        static::deleting(function ($product) {
            $product->options()->delete();
            $product->likes()->delete();
            $product->types()->delete();
        });
    }

/**
 * Get value field category_order same array
 * @param  [type] $category_other [description]
 * @return [type]                 [description]
 */
    public function getCategoryOtherAttribute($category_other)
    {
        if (is_string($category_other)) {
            return explode(',', $category_other);
        } else {
            return $category_other;
        }

    }

/**
 * Set value for field category_order
 * @param [type] $category_other [description]
 */
    public function setCategoryOtherAttribute($category_other)
    {
        if (is_array($category_other)) {
            $this->attributes['category_other'] = implode(',', $category_other);
        }

    }

/**
 * Get option of product from table option_detail
 * @return [type] [description]
 */
    public function getOptions()
    {
        $optionsType = ShopOption::where('status', 1)->pluck('name', 'id');
        $arrOptions  = array();
        foreach ($optionsType as $key => $value) {
            $check = ShopOptionDetail::where('option_id', $key)->where('product_id', $this->id)->pluck('name');
            if (count($check)) {
                $arrOptions[$value] = $check;
            }

        }
        return $arrOptions;

    }

/**
 * [getThumb description]
 * @return [type] [description]
 */
    public function getThumb()
    {
        $path_file = config('filesystems.disks.path_file', '');
        if (!file_exists($path_file . '/thumb/' . $this->image)) {
            return $path_file . '/' . $this->image;
        } else {
            return $path_file . '/thumb/' . $this->image;
        }
    }

/**
 * [getImage description]
 * @return [type] [description]
 */
    public function getImage()
    {
        $path_file = config('filesystems.disks.path_file', '');
        return $path_file . '/' . $this->image;

    }
/**
 * [getUrl description]
 * @return [type] [description]
 */
    public function getUrl()
    {
        return url('product/' . Scart::str_to_url($this->name) . '_' . $this->id . '.html');
    }
}
