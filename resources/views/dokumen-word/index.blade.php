@extends("layouts.app")

@section("content")
    <h1 class="feature-title">
        @lang("application.dokumen_word")
    </h1>

    <div class="my-3 d-flex justify-content-end">
        <a
                class="btn btn-primary"
                href="{{ route("dokumen-word.create") }}"
        >
            @lang("application.create")
        </a>
    </div>

    <div>
        @if($dokumen_words->isNotEmpty())
            <x-table>
                <x-thead>
                    <tr>
                        <th> @lang("application.number_symbol") </th>
                        <th> @lang("application.title") </th>
                        <th class="text-center"> @lang("application.controls") </th>
                    </tr>
                </x-thead>

                <tbody>
                @foreach ($dokumen_words as $dokumen_word)
                    <tr>
                        <td> {{ $dokumen_words->firstItem() + $loop->index }} </td>
                        <td> {{ $dokumen_word->nama }} </td>
                        <td class="text-center">
                            <a href="{{ route("dokumen-word.show", $dokumen_word) }}"
                               class="btn btn-primary btn-sm"
                            >
                                @lang("application.show")
                            </a>

                            <form
                                    class="d-inline-block"
                                    x-data="{}"
                                    @submit.prevent="confirmDialog().then(res => res.isConfirmed && $event.target.submit())"
                                    action="{{ route("dokumen-word.destroy", $dokumen_word) }}"
                                    method="POST"
                            >
                                @csrf
                                @method("DELETE")

                                <button class="btn btn-outline-danger btn-sm">
                                    @lang("application.destroy")
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </x-table>

            <div class="d-flex justify-content-center">
                {{ $dokumen_words->links() }}
            </div>

        @else
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                {{ __("messages.errors.no_data") }}
            </div>
        @endif
    </div>
@endsection