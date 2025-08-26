@extends('layouts.vendor.app')
@section('title', translate('late_delivery_requests'))

@section('content')
	<div class="content container-fluid">
		<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
			<h2 class="h1 mb-0 d-flex align-items-center gap-2">
				{{ translate('late_delivery_request_list') }}
				<span class="badge badge-soft-dark radius-50">{{ $lateList->total() }}</span>
			</h2>
		</div>
		<div class="card">
			<div class="table-responsive datatable-custom">
				<table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-start">
					<thead class="thead-light thead-50 text-capitalize">
						<tr>
							<th>{{ translate('SL') }}</th>
							<th>{{ translate('request_id') }}</th>
							<th>{{ translate('order_ID') }}</th>
							<th class="text-center">{{ translate('status') }}</th>
						</tr>
					</thead>
					<tbody>
					@foreach($lateList as $key=>$late)
						<tr>
							<td>{{ $lateList->firstItem()+$key }}</td>
							<td>{{ $late['id'] }}</td>
							<td><a class="title-color hover-c1" href="{{ route('vendor.orders.details',[$late->order_id]) }}">{{ $late->order_id }}</a></td>
							<td class="text-center"><span class="badge badge-soft-info text-capitalize">{{ str_replace('_',' ',$late['status']) }}</span></td>
						</tr>
					@endforeach
					</tbody>
				</table>
			</div>
			<div class="table-responsive mt-4"><div class="px-4 d-flex justify-content-lg-end">{!! $lateList->links() !!}</div></div>
			@if(count($lateList)==0)
				@include('layouts.vendor.partials._empty-state',['text'=>'no_data_found'],['image'=>'default'])
			@endif
		</div>
	</div>
@endsection


