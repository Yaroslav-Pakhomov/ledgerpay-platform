<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Трейт для удобного вызова авторизации, напр.:
     *
     * $this->authorize('update', $post);
     * $this->authorizeForUser($user, 'update', $post);
     * $this->authorizeResource(Post::class, 'post');
     */
    use AuthorizesRequests;
}
