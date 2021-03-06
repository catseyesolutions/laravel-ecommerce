<?php
namespace Mage2\Ecommerce\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mage2\Ecommerce\Models\Database\Attribute;
use Mage2\Ecommerce\Models\Database\Product;
use Mage2\Ecommerce\Http\Requests\ProductRequest;
use Mage2\Ecommerce\Image\Facade as Image;
use Illuminate\Support\Facades\File;
use Mage2\Ecommerce\DataGrid\Facade as DataGrid;
use Mage2\Ecommerce\Events\ProductAfterSave;
use Mage2\Ecommerce\Events\ProductBeforeSave;
use Mage2\Ecommerce\Models\Database\Property;

class ProductController extends AdminController
{

    /**
     * Display a listing of the resource.
     * r.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $dataGrid = DataGrid::model(Product::where('type','!=', 'VARIATION-COMBINATION'))
            ->column('id', ['sortable' => true])
            ->column('name')
            ->linkColumn('edit', [], function ($model) {
                return "<a href='" . route('admin.product.edit', $model->id) . "' >Edit</a>";

            })->linkColumn('destroy', [], function ($model) {
                return "<form id='admin-product-destroy-" . $model->id . "'
                                            method='POST'
                                            action='" . route('admin.product.destroy', $model->id) . "'>
                                        <input name='_method' type='hidden' value='DELETE' />
                                        " . csrf_field() . "
                                        <a href='#'
                                            onclick=\"jQuery('#admin-product-destroy-$model->id').submit()\"
                                            >Destroy</a>
                                    </form>";
            });


        return view('mage2-ecommerce::admin.product.index')->with('dataGrid', $dataGrid);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('mage2-ecommerce::admin.product.new-create');
    }

    /**
     * Store a newly created resource in storage.
     * @todo Change the ProductRequest Validation Rules for Store and Update
     *
     * @param \Mage2\Ecommerce\Http\Requests\ProductRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        try {
            $product = Product::create($request->all());
        } catch (\Exception $e) {
            echo 'Error in Saving Product: ', $e->getMessage(), "\n";
        }


        //rather then redirect we just execute Edit Method here.
        // Not sure if this is a good idea???

        //return $this->edit($product->id);
        return redirect()->route('admin.product.edit', ['id' => $product->id]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $product = Product::findorfail($id);
        //$properties = Collection::make([]);
        $properties =   Property::all()->pluck('name','id');


        if($product->type == "VARIATION") {
            //$properties =   Property::all()-
            $attributes =   Attribute::all()->pluck('name','id');
        }
        return view('mage2-ecommerce::admin.product.edit')
            ->with('model', $product)
            ->with('propertyOptions', $properties)
            ->with('attributeOptions', $attributes);
    }

    /**
     * Update the specified resource in storage.
     *
     * @var \Mage2\Ecommerce\Http\Requests\ProductRequest $request
     * @var int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProductRequest $request, $id)
    {

        try {
            Event::fire(new ProductBeforeSave($request));
            $product = Product::findorfail($id);
            $product->saveProduct($request);
            Event::fire(new ProductAfterSave($product, $request));

        } catch (\Exception $e) {
            throw new \Exception('Error in Saving Product: ' . $e->getMessage());
        }

        return redirect()->route('admin.product.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        Product::destroy($id);
        return redirect()->route('admin.product.index');
    }

    /**
     * upload image file and re sized it.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadImage(Request $request)
    {
        $image = $request->file('image');
        $tmpPath = str_split(strtolower(str_random(3)));
        $checkDirectory = '/uploads/catalog/images/' . implode('/', $tmpPath);

        $dbPath = $checkDirectory . "/" . $image->getClientOriginalName();

        $image = Image::upload($image, $checkDirectory);

        $tmp = $this->_getTmpString();

        return view('mage2-ecommerce::admin.product.upload-image')
            ->with('image', $image)
            ->with('tmp', $tmp);
    }

    /**
     * upload image file and resized it.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage(Request $request)
    {
        $path = $request->get('path');

        if (File::exists($path)) {
            File::delete(public_path() . $path);
        }

        return JsonResponse::create(['success' => true]);
    }

    /**
     * return random string only lower and without digits.
     *
     * @param int $length
     * @return string $randomString
     */
    public function _getTmpString($length = 6)
    {
        $pool = 'abcdefghijklmnopqrstuvwxyz';
        return substr(str_shuffle(str_repeat($pool, $length)), 0, $length);
    }
}