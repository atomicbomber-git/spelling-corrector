@extends("layouts.app")

@section("content")
    <h1 class="feature-title">
        @lang("application.dokumen_word")
    </h1>

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
                            <form
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