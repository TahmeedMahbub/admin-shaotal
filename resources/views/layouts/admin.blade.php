<!DOCTYPE html>
<html lang="en" class="light-style layout-navbar-fixed layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="{{ asset('assets') }}/" data-template="vertical-menu-template">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Admin') | Shaotal Admin</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/materialdesignicons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/admin-beige.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <style>.layout-menu-collapsed:not(.layout-menu-hover) .app-brand-img { display: none; }</style>
    @stack('styles')
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>
  </head>
  <body>
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
        @include('partials.sidebar')
        <div class="layout-page">
          @include('partials.navbar')
          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              @include('partials.flash')
              @yield('content')
            </div>
            <footer class="content-footer footer bg-footer-theme" style="background-color: var(--beige-bg-main) !important; border-top: 1px solid var(--beige-border-subtle);">
              <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                <div class="mb-2 mb-md-0 fs-8 text-muted">© {{ date('Y') }} Shaotal Admin.</div>
              </div>
            </footer>
          </div>
        </div>
      </div>
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    {{-- Shared delete confirmation (see x-admin.delete-button) --}}
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <form method="POST" id="deleteForm" class="modal-content">
          @csrf @method('DELETE')
          <div class="modal-header border-bottom pb-2">
            <h5 class="modal-title fs-6 fw-bold">Delete <span id="deleteLabel">item</span>?</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body py-3 fs-8 text-muted">This action cannot be undone.</div>
          <div class="modal-footer border-top pt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </div>
        </form>
      </div>
    </div>

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script>
      document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-delete-url]');
        if (!b) return;
        document.getElementById('deleteForm').action = b.dataset.deleteUrl;
        document.getElementById('deleteLabel').textContent = b.dataset.deleteLabel || 'item';
      });
      // Clicking anywhere on a table row with data-row-href navigates to it, unless the
      // click started on an interactive element (link, button, form control, drag handle).
      document.addEventListener('click', function (e) {
        var row = e.target.closest('tr[data-row-href]');
        if (!row) return;
        if (e.target.closest('a, button, input, select, textarea, label, .drag-handle, [data-bs-toggle]')) return;
        window.location = row.dataset.rowHref;
      });
      // Live image preview for <input type="file" data-preview="#imgId">
      document.addEventListener('change', function (e) {
        var f = e.target; if (!f.matches || !f.matches('input[type=file][data-preview]')) return;
        var box = document.querySelector(f.dataset.preview); if (!box || !f.files[0]) return;
        box.src = URL.createObjectURL(f.files[0]); box.classList.remove('d-none');
      });
    </script>
    <script src="{{ asset('assets/js/slug.js') }}?v={{ filemtime(public_path('assets/js/slug.js')) }}"></script>
    @stack('scripts')
  </body>
</html>
