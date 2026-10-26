<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageUploadException;
use App\Http\Controllers\Concerns\SavesProducts;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CloudinaryImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use SavesProducts;

    public function index(Request $request)
    {
        $rows = Product::query()
            ->select('id', 'name', 'sku', 'category_id', 'subcategory_id', 'brand_id', 'price', 'selling_price', 'has_variants', 'stock_qty', 'low_stock_threshold', 'availability', 'status', 'visibility')
            ->with(['category:id,name', 'subcategory:id,name', 'brand:id,name'])
            ->withCount('variants')
            ->withSum(['variants as variants_stock' => fn ($q) => $q->where('is_active', 1)], 'stock_qty')
            ->addSelect(['image_url' => ProductImage::select('image_url')->whereColumn('product_id', 'products.id')->orderByDesc('is_main')->orderBy('sort_order')->limit(1)])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('sku', 'like', "%$v%")))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->brand_id, fn ($q, $v) => $q->where('brand_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->stock === 'low', fn ($q) => $q->where(fn ($w) => $w
                ->where(fn ($s) => $s->where('has_variants', 0)->whereRaw('stock_qty <= COALESCE(low_stock_threshold, 5)'))
                ->orWhereExists(fn ($e) => $e->from('product_variants as v')->whereColumn('v.product_id', 'products.id')->where('v.is_active', 1)->whereRaw('v.stock_qty <= COALESCE(products.low_stock_threshold, 5)'))))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('products.index', [
            'rows' => $rows,
            'categories' => Category::orderBy('sort_order')->pluck('name', 'id'),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        $product = new Product(['has_variants' => 0, 'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'draft', 'stock_qty' => 0]);

        return view('products.form', $this->formData($product));
    }

    /** Opens the create form prefilled from an existing product; nothing is saved until the admin submits. */
    public function duplicate(Product $product)
    {
        $data = $this->formData($product);
        $state = $data['state'];

        // Drop row ids so everything is inserted as new (colour/size/image links are positional, so they survive).
        $state['images'] = array_map(fn ($i) => ['id' => null] + $i, $state['images']);
        $state['colors'] = array_map(fn ($c) => ['id' => ''] + $c, $state['colors']);
        $state['sizes'] = array_map(fn ($z) => ['id' => null] + $z, $state['sizes']);
        $state['variants'] = array_map(fn ($v) => ['id' => null, 'sku' => ''] + $v, $state['variants']);
        $state['slugManual'] = false;

        // Unique fields (slug, SKU) are left blank; the name and SEO title must not repeat the original.
        $copy = $product->replicate(['slug', 'sku', 'seo_title', 'created_by', 'updated_by']);
        $copy->name = Str::limit($product->name, 200, '').' (Copy)';
        $copy->status = 'draft';
        $copy->setRelation('specification', $product->specification);

        return view('products.form', array_merge($data, ['product' => $copy, 'state' => $state, 'duplicateOf' => $product]));
    }

    public function store(Request $request)
    {
        $input = $this->validated($request);
        $product = DB::transaction(fn () => $this->persist(new Product, $input));

        return redirect()->route('products.edit', $product)->with('success', $input['draft'] ? 'Draft saved. It is hidden from the storefront until published.' : 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product)
    {
        $input = $this->validated($request, $product);
        DB::transaction(fn () => $this->persist($product, $input));

        return redirect()->route('products.edit', $product)->with('success', $input['draft'] ? 'Draft saved. It is hidden from the storefront until published.' : 'Product updated.');
    }

    /** AJAX: upload one image to Cloudinary when it is picked, so progress/errors show early and the URL survives validation errors. */
    public function storeImage(Request $request, CloudinaryImageService $images): JsonResponse
    {
        if (! $request->hasFile('image')) {
            return response()->json(['message' => 'Please choose an image.'], 422);
        }

        try {
            return response()->json(['url' => $images->upload($request->file('image'), 'products')]);
        } catch (ImageUploadException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Product $product)
    {
        // Order history keeps product snapshots; never hard-delete a product that was ordered.
        if (DB::table('order_items')->where('product_id', $product->id)->exists()) {
            $product->update(['status' => 'archived', 'visibility' => 'hidden']);

            return redirect()->route('products.index')->with('success', 'Product has orders, so it was archived instead of deleted.');
        }

        // Variants hold RESTRICT foreign keys on colours/sizes, so they must go before the product cascade runs.
        return $this->destroyWith(fn () => DB::transaction(function () use ($product) {
            $product->variants()->delete();
            $product->delete();
        }), 'products.index', 'product');
    }
}
