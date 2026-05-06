<?php

namespace App\Http\Controllers;

use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->company) {
            return redirect()->route('dashboard');
        }

        return view('company.form', [
            'company' => null,
            'currencies' => Currency::common(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedCompany($request);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $request->user()->company()->create($validated);

        return redirect()->route('dashboard')->with('status', 'Company profile created.');
    }

    public function edit(Request $request): View
    {
        return view('company.form', [
            'company' => $request->user()->company,
            'currencies' => Currency::common(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $validated = $this->validatedCompany($request);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($validated);

        return redirect()->route('company.edit')->with('status', 'Company profile updated.');
    }

    private function validatedCompany(Request $request): array
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'default_currency_code' => ['required', 'string', 'size:3'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['default_currency_code'] = strtoupper($validated['default_currency_code']);
        unset($validated['logo']);

        return $validated;
    }
}
