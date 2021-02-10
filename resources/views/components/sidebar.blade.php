<nav class="col-md-2 h5" style="font-size: 12pt">
    <span class="d-block font-weight-bolder text-dark mb-3">
        MENU
    </span>

    <ul class="nav justify-content-start">
        <li class="nav-item {{ Route::is("dokumen-word.*") ? "active" : "" }}">
            <a class="nav-link pt-0"
               href="{{ route("dokumen-word.index") }}"
            >
                @lang("application.dokumen_word")
            </a>
        </li>
        <li class="nav-item {{ Route::is("word.*") ? "active" : "" }}">
            <a class="nav-link pt-0"
               href="{{ route("word.index") }}"
            >
                @lang("application.word_list")
            </a>
        </li>
    </ul>
</nav>