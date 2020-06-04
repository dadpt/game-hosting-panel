<?php

namespace App\Http\Controllers\User;

use App\Deploy;
use App\Http\Controllers\Controller;
use App\Location;
use App\Policies\UserPolicy;
use App\Server;
use App\Transaction;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Searchable\Search;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // Reflash messages to avoid them disappearing
        $request->session()->reflash();

        return redirect()->route('dashboard');
    }

    public function dashboard()
    {
        /** @var User $user */
        $user = Auth::user();
        $locations = Location::all();
        $servers = $user->servers()->with(['game', 'node', 'deploys'])->get();

        return view('dashboard', compact('servers', 'locations'));
    }

    public function search(Request $request)
    {
        $result = (new Search)
            ->registerModel(Server::class, 'name', 'ip')
            ->registerModel(Transaction::class, 'id')
            ->registerModel(Deploy::class, 'id', 'billing_period', 'transaction_id', 'server_id', 'termination_reason')
            ->search($request->input('term'));

        // Information about each model being searched
        $mapping = [
            'servers'      => [
                'title'    => __('words.servers'),
                'view'     => 'cards.servers',
                'variable' => 'servers',
            ],
            'transactions' => [
                'title'    => __('words.transactions'),
                'view'     => 'cards.transactions',
                'variable' => 'transactions',
            ],
            'deploys'      => [
                // TODO: deploys
                'title'    => __('words.deploy'),
                'view'     => 'cards.deploys',
                'variable' => 'deploys',
            ],
        ];

        // Pluck Models from each type group
        $result = $result->groupByType()->mapWithKeys(function (Collection $items, $type) {
            return [$type => $items->pluck('searchable')];
        });

        // Filter Models that user should not be able to see
        $result = $result->mapWithKeys(function (Collection $items, $type) use ($mapping) {
            return [$type => $items->filter(function ($model) use ($type, $mapping) {
                return Gate::allows('search', $model);
            })];
        });

        return view('search', compact('result', 'mapping'));
    }
}
