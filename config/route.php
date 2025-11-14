<?php
// +----------------------------------------------------------------------
// | 路由配置
// +----------------------------------------------------------------------

use think\facade\Route;

// 首页
Route::get('/', 'index/Index/index');
Route::get('/info', 'index/Index/info');
Route::get('/health', 'index/Index/health');

// 用户认证
Route::group('/auth', function () {
    Route::rule('/login', 'index/Auth/login', 'GET|POST');
    Route::rule('/register', 'index/Auth/register', 'GET|POST');
    Route::get('/logout', 'index/Auth/logout');
    Route::get('/userInfo', 'index/Auth/userInfo');
    Route::rule('/changePassword', 'index/Auth/changePassword', 'GET|POST');
})->allowCrossDomain();

// 简化的认证路由（兼容）
Route::rule('/login', 'index/Auth/login', 'GET|POST');
Route::rule('/register', 'index/Auth/register', 'GET|POST');
Route::get('/logout', 'index/Auth/logout');

// 课程管理
Route::group('/course', function () {
    Route::rule('/index', 'index/Course/index', 'GET|POST');
    Route::rule('/create', 'index/Course/create', 'GET|POST');
    Route::rule('/detail/:id', 'index/Course/detail', 'GET|POST');
    Route::rule('/edit/:id', 'index/Course/edit', 'GET|POST');
    Route::post('/delete/:id', 'index/Course/delete');
    Route::get('/getChapterTree', 'index/Course/getChapterTree');
    Route::get('/stats', 'index/Course/stats');
})->allowCrossDomain();

// 章节管理
Route::group('/chapter', function () {
    Route::get('/detail/:id', 'index/Chapter/detail');
    Route::rule('/edit/:id', 'index/Chapter/edit', 'GET|POST');
    Route::post('/save', 'index/Chapter/save');
    Route::post('/add', 'index/Chapter/add');
    Route::post('/delete/:id', 'index/Chapter/delete');
    Route::post('/updateSort', 'index/Chapter/updateSort');
    Route::get('/versionHistory', 'index/Chapter/versionHistory');
    Route::post('/restoreVersion', 'index/Chapter/restoreVersion');
    Route::get('/getStatus', 'index/Chapter/getStatus');
})->allowCrossDomain();

// AI生成
Route::group('/generate', function () {
    Route::post('/generateOutline', 'index/Generate/generateOutline');
    Route::post('/generateChapter', 'index/Generate/generateChapter');
    Route::post('/batchGenerate', 'index/Generate/batchGenerate');
    Route::get('/taskStatus/:task_id', 'index/Generate/taskStatus');
    Route::post('/optimizeText', 'index/Generate/optimizeText');
    Route::get('/testApi', 'index/Generate/testApi');
})->allowCrossDomain();

// 错误页面
Route::miss(function() {
    return json([
        'code' => 404,
        'message' => '页面不存在',
        'data' => [],
    ], 404);
});

return [];
