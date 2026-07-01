<?php
declare(strict_types=1);

use think\facade\Route;

Route::post('api/wx/login', 'WxController/login');
Route::get('api/user/profile', 'UserController/profile');
Route::post('api/user/profile/save', 'UserController/saveProfile');
Route::post('api/user/avatar/upload', 'UserController/uploadAvatar');
Route::get('api/category/list', 'CategoryController/list');
Route::post('api/category/create', 'CategoryController/create');
Route::post('api/record/create', 'RecordController/create');
Route::post('api/record/update', 'RecordController/update');
Route::post('api/record/delete', 'RecordController/delete');
Route::get('api/record/list', 'RecordController/list');
Route::get('api/record/latest-month', 'RecordController/latestMonth');
Route::get('api/record/detail', 'RecordController/detail');
Route::get('api/home/summary', 'HomeController/summary');
Route::get('api/budget/month', 'BudgetController/month');
Route::post('api/budget/save', 'BudgetController/save');
