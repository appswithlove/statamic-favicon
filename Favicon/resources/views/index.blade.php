@extends('layout')

@section('content')

    <form action="{{ route('favicon.gen') }}" method="post">
        {{ csrf_field() }}

        <div class="card flush flat-bottom">
            <div class="head">
                <h1>{{ $title }}</h1>

                <div class="controls">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            {{ $trans('generate_favicon') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <input type="hidden"
                   name="icon"
                   v-model="$refs.icon.data">
            <assets-fieldtype
                    :config="{
                folder: '/',
                max_files: 1,
                name: 'icon',
                type: 'assets',
                required: true,
                container: '{{ $assetContainer }}'
                }"
                    name="icon"
                    v-ref:icon
            ></assets-fieldtype>
        </div>
    </form>

@endsection
