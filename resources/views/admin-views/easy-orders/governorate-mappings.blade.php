@extends('layouts.admin.app')

@section('title', translate('EasyOrders_Governorate_Mappings'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('EasyOrders_Governorate_Mappings') }}</h2>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-3 mb-lg-0">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            {{ isset($editMapping) ? translate('Edit_Mapping') : translate('Add_New_Mapping') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <form
                            action="{{ isset($editMapping)
                                        ? route('admin.business-settings.easyorders.governorate-mappings.update', ['id' => $editMapping->id])
                                        : route('admin.business-settings.easyorders.governorate-mappings.store') }}"
                            method="post">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">
                                    {{ translate('EasyOrders_Governorate_Name') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="easyorders_name" class="form-control"
                                       value="{{ old('easyorders_name', $editMapping->easyorders_name ?? '') }}"
                                       placeholder="{{ translate('example') }}: القاهره و الجيزه">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    {{ translate('System_Governorate') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <select name="governorate_id" class="form-control">
                                    <option value="">{{ translate('select') }}</option>
                                    @foreach($governorates as $gov)
                                        <option value="{{ $gov->id }}"
                                            {{ (int)old('governorate_id', $editMapping->governorate_id ?? 0) === $gov->id ? 'selected' : '' }}>
                                            {{ $gov->name_ar }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                @if(isset($editMapping))
                                    <a href="{{ route('admin.business-settings.easyorders.governorate-mappings.index') }}"
                                       class="btn btn-secondary">
                                        {{ translate('cancel') }}
                                    </a>
                                @endif
                                <button type="submit" class="btn btn-primary">
                                    {{ isset($editMapping) ? translate('update') : translate('add') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Existing_Mappings') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ translate('EasyOrders_Governorate_Name') }}</th>
                                    <th>{{ translate('System_Governorate') }}</th>
                                    <th class="text-center">{{ translate('actions') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($mappings as $index => $mapping)
                                    <tr>
                                        <td>{{ $mappings->firstItem() + $index }}</td>
                                        <td>{{ $mapping->easyorders_name }}</td>
                                        <td>{{ $mapping->governorate?->name_ar ?? '-' }}</td>
                                        <td class="text-center">
                                            <a href="{{ route('admin.business-settings.easyorders.governorate-mappings.index', ['edit' => $mapping->id]) }}"
                                               class="btn btn-sm btn-outline-info">
                                                {{ translate('edit') }}
                                            </a>
                                            <form action="{{ route('admin.business-settings.easyorders.governorate-mappings.destroy', ['id' => $mapping->id]) }}"
                                                  method="post" class="d-inline-block"
                                                  onsubmit="return confirm('{{ translate('are_you_sure') }}');">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    {{ translate('delete') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            {{ translate('no_data_found') }}
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            {!! $mappings->links() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection



