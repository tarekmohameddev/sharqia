@extends('layouts.admin.app')
@section('title', translate('late_delivery_requests'))

@section('content')
	<div class="content container-fluid">
		<div class="mb-3">
			<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
				<h2 class="h1 mb-0">
					<img src="{{ dynamicAsset(path: 'public/assets/back-end/img/all-orders.png') }}" class="mb-1 mr-1" alt="">
					<span class="page-header-title">{{ translate('late_delivery_requests') }}</span>
				</h2>
				<span class="badge text-dark bg-body-secondary fw-semibold rounded-45">{{ $lateList->total() }}</span>
			</div>
		</div>
		<div class="card">
			<div class="p-3">
				<form action="{{ url()->current() }}" method="GET" class="max-w-280">
					<div class="input-group">
						<input type="search" name="searchValue" class="form-control" placeholder="{{ translate('search_by_order_id_or_request_id') }}" value="{{ request('searchValue') }}">
						<div class="input-group-append search-submit"><button type="submit"><i class="fi fi-rr-search"></i></button></div>
					</div>
				</form>
			</div>
			<div class="table-responsive">
				<table class="table table-hover table-borderless">
					<thead class="text-capitalize">
						<tr>
							<th>{{ translate('SL') }}</th>
							<th>{{ translate('request_id') }}</th>
							<th>{{ translate('order_ID') }}</th>
							<th class="text-capitalize">{{ translate('order_date') }}</th>
							<th class="text-capitalize">{{ translate('customer_info') }}</th>
							<th>{{ translate('store') }}</th>
							<th class="text-center">{{ translate('action') }}</th>
						</tr>
					</thead>
					<tbody>
					@foreach($lateList as $key => $late)
						<tr>
							<td>{{ $lateList->firstItem() + $key }}</td>
							<td>{{ $late['id'] }}</td>
							<td><a href="{{ route('admin.orders.details', ['id' => $late['order_id']]) }}">{{ $late['order_id'] }}</a></td>
							<td>
								@if($late->order)
									<div>{{ date('d M Y', strtotime($late->order->created_at)) }},</div>
									<div>{{ date('h:i A', strtotime($late->order->created_at)) }}</div>
								@endif
							</td>
							<td>
								@if($late->customer)
									<a class="d-flex align-items-center gap-2" href="{{ route('admin.customer.view', ['user_id' => $late->customer->id]) }}">
										<span>{{ $late->customer->f_name }} {{ $late->customer->l_name }}</span>
									</a>
								@else
									{{ translate('customer_not_found') }}
								@endif
							</td>
							<td>
								@if($late->order && $late->order->seller)
									<span>{{ $late->order->seller->shop->name ?? translate('inhouse') }}</span>
								@endif
							</td>
							<td class="text-center">
								<div class="d-flex justify-content-center gap-2">
									<span class="badge badge-soft-info text-capitalize">{{ str_replace('_',' ',$late['status']) }}</span>
									<a class="btn btn-outline-info btn-outline-info-dark icon-btn"
										title="{{ translate('view') }}"
										href="{{ route('admin.orders.details', ['id' => $late['order_id']]) }}">
										<i class="fi fi-sr-eye"></i>
									</a>
									<a class="btn btn-outline-success btn-outline-success-dark icon-btn"
										target="_blank" title="{{ translate('invoice') }}"
										href="{{ route('admin.orders.generate-invoice', [$late['order_id']]) }}">
										<i class="fi fi-sr-down-to-line"></i>
									</a>
									@if(\App\Utils\Helpers::module_permission_check('late_delivery_actions'))
									<button type="button" class="btn btn-outline-info btn-outline-info-dark icon-btn js-late-status" data-id="{{ $late['id'] }}" data-status="in_progress" title="{{ translate('in_progress') }}">
										<i class="fi fi-rr-time-forward"></i>
									</button>
									<button type="button" class="btn btn-outline-success btn-outline-success-dark icon-btn js-late-status" data-id="{{ $late['id'] }}" data-status="resolved" title="{{ translate('resolved') }}">
										<i class="fi fi-sr-check"></i>
									</button>
									<button type="button" class="btn btn-outline-danger btn-outline-danger-dark icon-btn js-late-status" data-id="{{ $late['id'] }}" data-status="rejected" title="{{ translate('rejected') }}">
										<i class="fi fi-sr-cross-small"></i>
									</button>
									@endif
								</div>
							</td>
						</tr>
					@endforeach
					</tbody>
				</table>
			</div>
			<div class="table-responsive mt-4">
				<div class="px-4 d-flex justify-content-lg-end">{!! $lateList->links() !!}</div>
			</div>
			@if(count($lateList)==0)
				@include('layouts.admin.partials._empty-state',['text'=>'no_data_found'],['image'=>'default'])
			@endif
		</div>
	</div>
@endsection

@push('script')
<script>
    $(document).on('click', '.js-late-status', function () {
        const $btn = $(this);
        const id = $btn.data('id');
        const status = $btn.data('status');

        let payload = {
            _token: '{{ csrf_token() }}',
            id: id,
            late_status: status
        };

        if (status === 'rejected') {
            const note = prompt('{{ translate('please_enter_rejected_note') }}');
            if (note === null || note.trim() === '') {
                toastMagic.error('{{ translate('The_rejected_note_field_is_required') }}');
                return;
            }
            payload.rejected_note = note.trim();
        } else if (status === 'resolved') {
            const note = prompt('{{ translate('optional') }} - {{ translate('enter_resolved_note_if_any') }}');
            if (note && note.trim() !== '') {
                payload.resolved_note = note.trim();
            }
        }

        $btn.prop('disabled', true);
        $.post({
            url: '{{ route('admin.late-delivery.status-update') }}',
            data: payload
        }).done(function (res) {
            toastMagic.success(res.message || '{{ translate('status_updated_successfully') }}');
            window.location.reload();
        }).fail(function (xhr) {
            const msg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || '{{ translate('something_went_wrong') }}';
            toastMagic.error(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });
</script>
@endpush


