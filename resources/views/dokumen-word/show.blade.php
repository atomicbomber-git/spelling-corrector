@extends("layouts.app")

@section("content")
    <div id="app">
        <dokumen-word-show
                data-url="{{ route("dokumen-word.show", $dokumen_word)}}"
                recommender-url="{{ route("rekomendasi-pembenaran") }}"
        >

        </dokumen-word-show>
    </div>
{{--    <livewire:dokumen-word-show :dokumen-word="$dokumen_word" />--}}
@endsection