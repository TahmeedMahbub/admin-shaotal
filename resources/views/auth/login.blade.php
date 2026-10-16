<!DOCTYPE html>
<html lang="en" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="{{ asset('assets') }}/" data-template="vertical-menu-template">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0" />
    <title>Login | Shaotal Admin</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/materialdesignicons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/admin-beige.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />
  </head>
  <body>
    <div class="position-relative">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
          <div class="card p-2">
            <div class="app-brand justify-content-center mt-5">
              <span class="app-brand-link"><img src="{{ asset('assets/img/logo/logo1.png') }}" alt="Shaotal Admin" style="height: 48px; width: auto; max-width: 100%;"></span>
            </div>
            <div class="card-body mt-2">
              <h4 class="mb-2 fw-semibold">Welcome back 👋</h4>
              <p class="mb-4">Please sign in to your account</p>
              @if ($errors->any())<div class="alert alert-danger fs-8">{{ $errors->first() }}</div>@endif
              <form class="mb-3" action="{{ route('login.attempt') }}" method="POST">
                @csrf
                <div class="form-floating form-floating-outline mb-3">
                  <input type="text" class="form-control" id="login" name="login" value="{{ old('login') }}" placeholder="Email or username" autocomplete="username" required autofocus />
                  <label for="login">Email or Username</label>
                </div>
                <div class="mb-3">
                  <div class="form-floating form-floating-outline">
                    <input type="password" id="password" class="form-control" name="password" placeholder="Password" autocomplete="current-password" required />
                    <label for="password">Password</label>
                  </div>
                </div>
                <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>
