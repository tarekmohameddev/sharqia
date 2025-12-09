<?php

namespace App\Enums\ViewPaths\Admin;

class LateDelivery
{
	const LIST = [
		URI => 'list',
		VIEW => 'admin-views.late-delivery.list',
	];

	const DETAILS = [
		URI => 'details',
		VIEW => 'admin-views.late-delivery.details',
	];

	const UPDATE_STATUS = [
		URI => 'status-update',
		VIEW => '',
	];

	const FLAG = [
		URI => 'flag',
		VIEW => '',
	];
}


