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

    @if ($hasFavicon)
        <div class="card flush flat-bottom">
            <div class="head">
                <h1>{{ $trans('current_favicon') }}</h1>

                <div class="controls">
                    <div class="btn-group">
                        <a href="{{ route('favicon.remove') }}" class="btn">
                            {{ $trans('remove_favicon') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card flush">
            <div style="text-align: center;">
                <img src="{{ $preview }}"/>
            </div>

            <div class="clearfix">
                <div class="col-sm-6">
                    <h3>{{ $trans('template_code') }}</h3>
                    <pre>{!! $partialTag !!}</pre>
                </div>

                <div class="col-sm-6">
                    <h3>{{ $trans('html_code') }}</h3>
                    <pre>{{ $htmlCode }}</pre>
                </div>
            </div>
        </div>
    @endif

@endsection
