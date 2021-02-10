<div>
    <h1 class="feature-title">
        <a href="{{ route("dokumen-word.index") }}">
            @lang("application.dokumen_word")
        </a>

        /

        @lang("application.show")
    </h1>

{{--    <div class="card mb-3">--}}
{{--        <div class="card-body">--}}
{{--            <button class="btn btn-primary">--}}
{{--                Click Me Click Me--}}
{{--            </button>--}}
{{--        </div>--}}
{{--    </div>--}}

    <div class="card">
        <div wire:ignore class="card-body">
            <div class="form-group">
                <label for="konten_html"> @lang("application.content"): </label>
                <textarea
                        id="konten_html"
                        type="text"
                        placeholder="Konten"
                        class="form-control @error("konten_html") is-invalid @enderror"
                        name="konten_html"
                ></textarea>
                @error("konten_html")
                <span class="invalid-feedback">
                {{ $message }}
            </span>
                @enderror
            </div>
        </div>
    </div>

    @push("scripts")
        <script>
            tinyMCE.init(Object.assign(window.tinymce_settings, {
                selector: '#konten_html',
                content_css: '{{ asset('css/app.css') }}',
            })).then(editors => {
                editors[0].setContent(`{!! old('content', $dokumen_word->konten_html) !!}`)
            })
        </script>
    @endpush
</div>