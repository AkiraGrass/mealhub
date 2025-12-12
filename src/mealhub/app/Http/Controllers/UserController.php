<?php

namespace App\Http\Controllers;

use App\Helper\ApiResponse;
use App\Helper\Status;
use App\Http\Requests\UserUpdateSelfRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function me(Request $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        $user = User::findOrFail($userId);
        return ApiResponse::success((new UserResource($user))->toArray($request));
    }

    public function update(UserUpdateSelfRequest $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $data = $request->validated();
        $user = User::findOrFail($userId);

        $payload = [];
        if (isset($data['firstName'])) $payload['first_name'] = $data['firstName'];
        if (isset($data['lastName']))  $payload['last_name']  = $data['lastName'];
        if (isset($data['phone']))     $payload['phone']      = $data['phone'];
        if (isset($data['password']))  $payload['password']   = Hash::make($data['password']);

        $user->update($payload);
        return ApiResponse::success((new UserResource($user))->toArray($request));
    }
}
