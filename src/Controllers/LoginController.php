<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Support\Auth;

final class LoginController extends Controller
{
    public function show(Request $request): void
    {
        if (Auth::check()) {
            redirect('/');
        }

        $this->render('auth/login', ['pageTitle' => 'Sign in'], 'auth');
    }

    public function attempt(Request $request): void
    {
        $password = (string) $request->input('password', '');

        if (Auth::attempt($password)) {
            flash('success', 'Welcome back.');
            redirect('/');
        }

        flash('error', 'Incorrect password.');
        redirect('/login');
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        flash('success', 'Signed out.');
        redirect('/login');
    }
}
