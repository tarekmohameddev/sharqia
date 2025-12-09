@extends('layouts.admin.app')

@section('title', translate('city_zone_shipping_management'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <h2 class="h1 mb-0">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/shipping.png') }}" class="mb-1 mr-1" alt="">
                <span class="page-header-title">{{ translate('city_zone_shipping_management') }}</span>
            </h2>
            <span class="badge text-dark bg-body-secondary fw-semibold rounded-45">{{ $governorates->total() }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0">
            <div class="row justify-content-between align-items-center flex-grow-1">
                <div class="col-md-4">
                    <h5 class="mb-0">{{ translate('shipping_cost_list') }}</h5>
                </div>
                <div class="col-md-6">
                    <form action="{{ route('admin.shipping.city-zone-shipping.index') }}" method="GET">
                        <div class="input-group input-group-merge input-group-custom">
                            <div class="input-group-prepend">
                                <div class="input-group-text">
                                    <i class="fi fi-rr-search"></i>
                                </div>
                            </div>
                            <input type="search" name="searchValue" class="form-control" 
                                   placeholder="{{ translate('search_by_city_name') }}" 
                                   value="{{ $searchValue }}">
                            <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-2 text-end">
                    <a href="{{ route('admin.shipping.city-zone-shipping.create') }}" class="btn btn-primary">
                        <i class="fi fi-rr-plus"></i> {{ translate('add_new') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ translate('sl') }}</th>
                        <th>{{ translate('city_name') }}</th>
                        <th>{{ translate('shipping_cost') }}</th>
                        <th>{{ translate('created_at') }}</th>
                        <th class="text-center">{{ translate('action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($governorates as $key => $governorate)
                        <tr>
                            <td>{{ $governorates->firstItem() + $key }}</td>
                            <td>
                                <span class="font-weight-semibold">{{ $governorate->name_ar }}</span>
                            </td>
                            <td>
                                @if($governorate->shippingCost)
                                    <span class="badge bg-soft-success text-success font-weight-bold">
                                        {{ setCurrencySymbol(amount: usdToDefaultCurrency(amount: $governorate->shippingCost->cost)) }}
                                    </span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">
                                        {{ translate('not_set') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{ $governorate->shippingCost ? $governorate->shippingCost->created_at->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    @if($governorate->shippingCost)
                                        <a href="{{ route('admin.shipping.city-zone-shipping.show', $governorate->shippingCost->id) }}" 
                                           class="btn btn-outline-info btn-sm" title="{{ translate('view') }}">
                                            <i class="fi fi-sr-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.shipping.city-zone-shipping.edit', $governorate->shippingCost->id) }}" 
                                           class="btn btn-outline-primary btn-sm" title="{{ translate('edit') }}">
                                            <i class="fi fi-rr-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-btn" 
                                                data-action="{{ route('admin.shipping.city-zone-shipping.destroy', $governorate->shippingCost->id) }}"
                                                title="{{ translate('delete') }}">
                                            <i class="fi fi-rr-trash"></i>
                                        </button>
                                    @else
                                        <a href="{{ route('admin.shipping.city-zone-shipping.create', ['governorate_id' => $governorate->id]) }}" 
                                           class="btn btn-outline-success btn-sm" title="{{ translate('add_shipping_cost') }}">
                                            <i class="fi fi-rr-plus"></i> {{ translate('add_cost') }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">
                                <div class="text-center p-4">
                                    <img class="mb-3 w-160" src="{{ dynamicAsset(path: 'public/assets/back-end/img/empty-state-icon/default.png') }}" alt="">
                                    <p class="mb-0">{{ translate('no_data_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-responsive mt-4">
            <div class="px-4 d-flex justify-content-lg-end">
                {{ $governorates->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">{{ translate('delete_confirmation') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ translate('are_you_sure_you_want_to_delete_this_shipping_cost') }}?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('cancel') }}</button>
                <form id="deleteForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">{{ translate('delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    $(document).ready(function() {
        $('.delete-btn').on('click', function() {
            let action = $(this).data('action');
            $('#deleteForm').attr('action', action);
            $('#deleteModal').modal('show');
        });
    });
</script>
@endpush
