<?php

namespace App\Http\Controllers;

use App\Models\CustomerSite;
use Illuminate\Http\Request;

class CustomerSiteController extends Controller
{
    public function index(Request $request)
    {
        $customerSiteQuery = CustomerSite::query();
        $customerSiteQuery->where('name', 'like', '%'.$request->get('q').'%');
        $customerSiteQuery->orderBy('name');
        $customerSites = $customerSiteQuery->paginate(25);

        return view('customer_sites.index', compact('customerSites'));
    }

    public function create()
    {
        $this->authorize('create', new CustomerSite);

        return view('customer_sites.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', new CustomerSite);

        $newCustomerSite = $request->validate([
            'name' => 'required|max:60',
            'url' => 'required|max:255',
        ]);
        $newCustomerSite['creator_id'] = auth()->id();

        $customerSite = CustomerSite::create($newCustomerSite);

        return redirect()->route('customer_sites.show', $customerSite);
    }

    public function show(CustomerSite $customerSite)
    {
        return view('customer_sites.show', compact('customerSite'));
    }

    public function edit(CustomerSite $customerSite)
    {
        $this->authorize('update', $customerSite);

        return view('customer_sites.edit', compact('customerSite'));
    }

    public function update(Request $request, CustomerSite $customerSite)
    {
        $this->authorize('update', $customerSite);

        $customerSiteData = $request->validate([
            'name' => 'required|max:60',
            'url' => 'required|max:255',
            'is_active' => 'required|in:0,1',
        ]);
        $customerSite->update($customerSiteData);

        return redirect()->route('customer_sites.show', $customerSite);
    }

    public function destroy(Request $request, CustomerSite $customerSite)
    {
        $this->authorize('delete', $customerSite);

        $request->validate(['customer_site_id' => 'required']);

        if ($request->get('customer_site_id') == $customerSite->id && $customerSite->delete()) {
            return redirect()->route('customer_sites.index');
        }

        return back();
    }
}
