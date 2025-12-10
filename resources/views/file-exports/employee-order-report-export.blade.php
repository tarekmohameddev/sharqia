<table>
    <thead>
    <tr>
        <th>{{ translate('employee_Name') }}</th>
        <th>{{ translate('role') }}</th>
        <th>{{ translate('total_Orders_Created') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($stats as $row)
        @php($employee = $row->createdByAdmin)
        <tr>
            <td>
                @if($employee)
                    {{ $employee->name }}
                @else
                    {{ translate('N/A') }}
                @endif
            </td>
            <td>
                @if($employee && $employee->role)
                    {{ $employee->role->name }}
                @else
                    {{ translate('N/A') }}
                @endif
            </td>
            <td>{{ $row->total_orders }}</td>
        </tr>
    @endforeach
    </tbody>
</table>


