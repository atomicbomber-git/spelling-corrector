<template>
    <div>
        <div class="card">
            <div class="card-body">
                <label for="text">
                    Teks
                </label>
                <textarea
                    class="form-control"
                    id="text"
                    v-model="text"
                    placeholder="Teks"
                    rows="20"
                ></textarea>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md">
                    <span v-for="token in tokens"
                          @click="onTokenClick(token)"
                          class="mx-1"
                          :class="{
                            'badge': token.incorrect,
                            'badge-danger': token.incorrect && token.pickedCorrection === null,
                            'badge-success': token.incorrect && token.pickedCorrection !== null,
                      }"
                    > {{ tokenDisplay(token) }} </span>
                    </div>

                    <div v-if="selectedToken !== null"
                         class="col-md-3">
                        <h5> Koreksi untuk "{{ selectedToken.cleaned }}"</h5>

                        <ul class="list-group">
                            <li
                                @click="onCorrectionOptionClick(correction)"
                                class="list-group-item list-group-item-action"
                                :class="{ active: selectedToken.pickedCorrection === correction}"
                                v-for="correction in selectedToken.corrections"
                            > {{ correction }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import {debounce} from 'lodash'

    export default {
        data() {
            return {
                text: null,
                tokens: [],
                selectedToken: null,
            }
        },

        methods: {
            tokenDisplay(token) {
                if (token.pickedCorrection === null) {
                    return token.original
                }

                return token.original.toLowerCase()
                    .replace(token.cleaned, token.pickedCorrection)
            },

            onTokenClick(token) {
                if (token.incorrect) {
                    this.selectedToken = token
                } else {
                    this.selectedToken = null
                }
            },

            onCorrectionOptionClick(correction) {
                this.tokens = this.tokens.map(tok => {
                    if (this.selectedToken.id === tok.id) {
                        this.selectedToken = {...tok, pickedCorrection: correction}
                        return this.selectedToken
                    }
                    return tok;
                })
            }
        },

        watch: {
            text: debounce(function (text) {
                axios.post("/", {text: text})
                    .then(response => {
                        this.tokens = response.data.map((token, index) => ({
                            ...token,
                            id: index,
                            pickedCorrection: null,
                        }))
                    })
                    .catch(error => {
                        console.log(error)
                    })
            }, 400)
        }
    }
</script>
