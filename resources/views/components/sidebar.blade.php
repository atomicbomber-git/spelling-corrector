<nav class="col-md-2 h5">
    <ul class="nav justify-content-start">
        <li class="nav-item {{ Route::is("dokumen-word.*") ? "active" : "" }}">
            <a class="nav-link"
               href="{{ route("dokumen-word.index") }}"
            >
                @lang("application.dokumen_word")
            </a>
        </li>
        <li class="nav-item {{ Route::is("word.*") ? "active" : "" }}">
            <a class="nav-link"
               href="{{ route("word.index") }}"
            >
                @lang("application.word_list")
            </a>
        </li>
    </ul>
</nav>