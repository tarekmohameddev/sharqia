@if (count($products) > 0)
    @foreach ($products as $product)
        @include('admin-views.pos.partials._single-product',['product'=>$product,'formIdPrefix'=>'search'])
    @endforeach
@else
    <div>
        <h5 class="m-0 text-muted">{{ translate('No_Product_Found') }}</h5>
    </div>
@endif
