@extends('layout')

@section('content')

    <form action="{{ $target['url'] }}" method="post" id="redirect-form">
        @foreach ($target['params'] as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
        @endforeach

        <div class="card flush flat-bottom">
            <div class="head">
                <h1>{{ $title }}</h1>
            </div>
        </div>

        <div class="card">
            <div class="no-results">
                <span class="icon icon-aircraft-take-off"></span>
                <h2>{{ $trans('will_redirect') }}</h2>
                <button type="submit" class="btn btn-default btn-lg">
                    {{ $trans('continue') }}
                </button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('redirect-form');
            form.submit();
        })
    </script>

@endsection
