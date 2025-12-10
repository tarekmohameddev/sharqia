@extends('layouts.admin.app')
@section('title', translate('employee_order_Report'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{ dynamicAsset(path: 'public/assets/back-end/img/order_report.png')}}" alt="">
                {{ translate('employee_order_Report') }}
            </h2>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form action="" id="form-data" method="GET">
                    <h3 class="mb-3">{{ translate('filter_Data') }}</h3>
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-md-3">
                            <label class="mb-2">{{ translate('select_Date') }}</label>
                            <div class="select-wrapper">
                                <select class="form-select" name="date_type" id="date_type">
                                    @php($currentDateType = $date_type ?? 'this_year')
                                    <option value="this_year" {{ $currentDateType == 'this_year' ? 'selected' : '' }}>{{translate('this_Year')}}</option>
                                    <option value="this_month" {{ $currentDateType == 'this_month' ? 'selected' : '' }}>{{translate('this_Month')}}</option>
                                    <option value="this_week" {{ $currentDateType == 'this_week' ? 'selected' : '' }}>{{translate('this_Week')}}</option>
                                    <option value="today" {{ $currentDateType == 'today' ? 'selected' : '' }}>{{translate('today')}}</option>
                                    <option value="custom_date" {{ $currentDateType == 'custom_date' ? 'selected' : '' }}>{{translate('custom_Date')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div>
                                <label class="mb-2">{{ ucwords(translate('start_date'))}}</label>
                                <input type="date" name="from" value="{{ $from }}" id="from_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div>
                                <label class="mb-2">{{ ucwords(translate('end_date'))}}</label>
                                <input type="date" value="{{ $to }}" name="to" id="to_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3 filter-btn">
                            <button type="submit" class="btn btn-primary">
                                {{ translate('filter') }}
                            </button>
                        </div>
                        <div class="col-sm-6 col-md-3 filter-btn">
                            <a href="{{ route('admin.report.employee-order-export-excel', ['date_type' => $currentDateType, 'from' => $from, 'to' => $to]) }}"
                               class="btn btn-outline-primary">
                                {{ translate('export_Excel') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <div class="card card-body flex-grow-1">
                        <div class="d-flex gap-3 align-items-center">
                            <img width="35" src="{{dynamicAsset(path: 'public/assets/back-end/img/cart.svg')}}" alt="{{translate('image')}}">
                            <div class="info">
                                <h4 class="subtitle h1">{{ $totalOrders }}</h4>
                                <h5 class="subtext">{{ translate('total_Orders_Created') }}</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('employee_Name') }}</th>
                            <th>{{ translate('role') }}</th>
                            <th>{{ translate('total_Orders_Created') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($stats as $index => $row)
                            @php($employee = $row->createdByAdmin)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if($employee)
                                        {{ $employee->name }}<br>
                                        <small class="text-muted">{{ $employee->email }}</small>
                                    @else
                                        <span class="text-muted">{{ translate('N/A') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($employee && $employee->role)
                                        {{ $employee->role->name }}
                                    @else
                                        <span class="text-muted">{{ translate('N/A') }}</span>
                                    @endif
                                </td>
                                <td>{{ $row->total_orders }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    {{ translate('no_data_found') }}
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

