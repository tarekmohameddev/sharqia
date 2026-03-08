@extends('layouts.admin.app')

@section('title', translate('Generate_Code_Batch'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ translate('Generate_New_Batch') }}</h2>
            <a href="{{ route('admin.authenticity.batches.index') }}" class="btn btn-outline-secondary">
                {{ translate('Back') }}
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Batch_Settings') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.authenticity.batches.store') }}" method="POST">
                            @csrf

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    {{ translate('Number_of_Codes') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="number"
                                       name="count"
                                       class="form-control @error('count') is-invalid @enderror"
                                       min="1"
                                       max="10000"
                                       value="{{ old('count', 500) }}"
                                       required>
                                @error('count')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    {{ translate('Enter_between_1_and_10000_codes_per_batch') }}.
                                    {{ translate('For_larger_batches_generate_multiple_batches') }}.
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <strong>{{ translate('Code_Format') }}:</strong>
                                {{ translate('Each_code_is_12_characters_using_charset_that_avoids_ambiguous_characters') }}
                                (0/O, 1/I/L {{ translate('excluded') }}).
                                <br>
                                {{ translate('Example') }}: <code>A3K9B7MX2PQW</code>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fi fi-sr-magic-wand"></i>
                                    {{ translate('Generate_Batch') }}
                                </button>
                                <a href="{{ route('admin.authenticity.batches.index') }}" class="btn btn-outline-secondary">
                                    {{ translate('Cancel') }}
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
