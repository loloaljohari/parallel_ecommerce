<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/process', function (Request $request) {
    $port = $request->getPort();
    $task = (int) $request->query('task', 0);

    return response()->json([
        'task' => $task,
        'message' => "Task {$task} -> Handled by node on port {$port}",
        'node_port' => $port,
    ]);
});
