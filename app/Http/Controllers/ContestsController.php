<?php

namespace App\Http\Controllers;

use App\Contest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContestsController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth')->except('index', 'show');
    }
    
    /**
     * Show all contests.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = [
            'navigation' => [
                'List of Contest'
            ]
        ];

        return view('contests.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = [
            'navigation' => [
                'Contests',
                'Create Contest',
            ]
        ];

        return view('contests.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // validate all request
        $this->validate($request, [
            'name'           => 'required|unique:contests',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date',
            'access_level'   => 'required',
            'contest_amount' => 'required|numeric',
            'max_amount'     => 'required|numeric',
            'max_member'     => 'required|numeric'
        ]);
        
        // create contest
        $contest = auth()->user()->contests()->create([
            'name'           => $request->name,
            'start_date'     => Carbon::parse($request->start_date),
            'end_date'       => Carbon::parse($request->end_date),
            'access_level'   => $request->access_level,
            'contest_amount' => $request->contest_amount,
            'max_amount'     => $request->max_amount,
            'max_member'     => $request->max_member
        ]);

        // return session msg

        return redirect('contests/dashboard');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Contest  $contest
     * @return \Illuminate\Http\Response
     */
    public function show(Contest $contest)
    {
        // Retrieve all contests that have at least one approved user..
        $contest->load(['contestUsers' => function ($q) {
            $q->wherePivot('approved', true);
        }]);

        return $contest;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contest  $contest
     * @return \Illuminate\Http\Response
     */
    public function edit(Contest $contest)
    {
        // Only the creator can edit his/here contest.
        $this->authorize('edit', $contest);

        $data = [
            'navigation' => [
                'Contests',
                'Edit Contest',
            ]
        ];

        return view('contests.edit', compact('data', 'contest'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Contest  $contest
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Contest $contest)
    {
        // Only the creator can update his/here contest.
        $this->authorize('update', $contest);

        // validate all request
        $this->validate($request, [
            'name'           => 'required|unique:contests,id,'.$contest->id,
            'start_date'     => 'required|date',
            'end_date'       => 'required|date',
            'access_level'   => 'required',
            'contest_amount' => 'required|numeric',
            'max_amount'     => 'required|numeric',
            'max_member'     => 'required|numeric'
        ]);

        $contest->update([
            'name'           => $request->name,
            'start_date'     => Carbon::parse($request->start_date),
            'end_date'       => Carbon::parse($request->end_date),
            'access_level'   => $request->access_level,
            'contest_amount' => $request->contest_amount,
            'max_amount'     => $request->max_amount,
            'max_member'     => $request->max_member
        ]);

        // return session msg
        
        return redirect('contests/dashboard');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Contest  $contest
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contest $contest)
    {
        //
    }
}
