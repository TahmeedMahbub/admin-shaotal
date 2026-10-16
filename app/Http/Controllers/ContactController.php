<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public const STATUSES = ['new', 'in_progress', 'resolved', 'spam'];

    public function index(Request $request)
    {
        $rows = ContactMessage::query()
            ->select('id', 'name', 'email', 'phone', 'subject', 'status', 'created_at')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")->orWhere('subject', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('contacts.index', ['rows' => $rows, 'statuses' => self::STATUSES]);
    }

    public function create()
    {
        return view('contacts.form', $this->formData(new ContactMessage(['status' => 'new'])));
    }

    public function store(Request $request)
    {
        $contact = ContactMessage::create($this->validated($request));

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact message logged.');
    }

    public function show(ContactMessage $contact)
    {
        $contact->load('assignee:id,name');

        return view('contacts.show', ['contact' => $contact, 'statuses' => self::STATUSES]);
    }

    public function edit(ContactMessage $contact)
    {
        return view('contacts.form', $this->formData($contact));
    }

    public function update(Request $request, ContactMessage $contact)
    {
        $contact->update($this->validated($request));

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function destroy(ContactMessage $contact)
    {
        return $this->destroyWith(fn () => $contact->delete(), 'contacts.index', 'contact message');
    }

    private function formData(ContactMessage $contact): array
    {
        return ['contact' => $contact, 'statuses' => self::STATUSES, 'admins' => AdminUser::where('status', 'active')->pluck('name', 'id')];
    }

    private function validated(Request $request): array
    {
        return collect($request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:220'],
            'message' => ['required', 'string', 'max:10000'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:admin_users,id'],
        ]))->map(fn ($v) => $v === '' ? null : $v)->all();
    }
}
