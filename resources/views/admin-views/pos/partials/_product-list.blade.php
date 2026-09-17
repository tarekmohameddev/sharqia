                    <div class="card-body pt-2 pb-80 overflow-hidden" id="items">
                        @if(count($products) > 0)
                            <div class="pos-item-wrap-horizontal max-h-100vh-350px">
                                @foreach($products as $product)
                                    @include('admin-views.pos.partials._single-product',['product'=>$product])
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 bg-chat rounded text-center">
                                <div class="py-5">
                                    <img src="http://localhost/Backend-6Valley-eCommerce-CMS/public/assets/back-end/img/empty-product.png" width="64" alt="">
                                    <div class="mx-auto my-3 max-w-353px">
                                        {{ translate('Currently_no_product_available_by_this_name') }}
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>
                    <div class="table-responsive bottom-absolute-buttons">
                        <div class="px-4 d-flex justify-content-lg-end">
                            {!!$products->withQueryString()->links()!!}
                        </div>
                    </div>
