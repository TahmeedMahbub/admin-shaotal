<nav class="layout-navbar navbar navbar-expand-xl align-items-center" id="layout-navbar">
  <div class="container-fluid d-flex align-items-center justify-content-between px-0">
    <div class="d-flex align-items-center gap-3">
      <a class="nav-item nav-link px-0 me-3 layout-menu-toggle" href="javascript:void(0)"><i class="mdi mdi-menu mdi-24px"></i></a>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 py-0" style="font-size: 0.875rem;">
          <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Admin</a></li>
          @hasSection('section')<li class="breadcrumb-item">@yield('section')</li>@endif
          <li class="breadcrumb-item active" aria-current="page">@yield('title')</li>
        </ol>
      </nav>
    </div>
    <div class="dropdown">
      <a href="#" class="d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
        <span class="fs-8 text-muted d-none d-md-inline">{{ auth('admin')->user()->name }}</span>
        <div class="avatar avatar-online"><span class="avatar-initial rounded-circle fw-bold" style="background-color: var(--beige-primary-accent); color: var(--beige-text-light);">{{ strtoupper(substr(auth('admin')->user()->name, 0, 1)) }}</span></div>
      </a>
      <ul class="dropdown-menu dropdown-menu-end">
        <li class="px-3 py-2 fs-8 text-muted">{{ auth('admin')->user()->email }}</li>
        <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="mdi mdi-logout me-2"></i>Log out</button></form></li>
      </ul>
    </div>
  </div>
</nav>
