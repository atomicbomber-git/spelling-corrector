<template>
  <div>
    <div v-if="dokumen">
      <form class="d-block card mb-3"
            @submit.prevent="onFormSubmit"
      >
        <div class="card-header">
          Rekomendasi Koreksi Ejaan
        </div>

        <div class="card-body">
          <!-- Corrections / Recommendations -->
          <div
              v-if="Object.keys(this.mTokensWithError).length > 0"
              style="height: 480px; overflow-y: scroll"
          >
            <table
                class="table table-sm table-striped"
                style="table-layout: fixed"
            >
              <thead class="thead thead-dark">
              <tr>
                <th style="width: 100%"> Kata</th>
                <th style="width: 100%"> Rekomendasi</th>
                <th style="width: 100%"> Koreksi</th>
              </tr>
              </thead>

              <tbody>

              <template v-for="(tokenWithError, tokenString) in mTokensWithError">
                <tr :key="tokenString">
                  <td class="font-weight-bold h5"
                      colspan="3"
                  >
                    {{ tokenString }}
                  </td>
                </tr>

                <template v-for="(errorPosition, index) in tokenWithError.positions">
                  <tr :key="`${tokenString}-${index}`">
                    <td>
                      <button
                          class="btn btn-sm btn-light"
                          type="button"
                          @click="onDisplaySentenceButtonClick(errorPosition)"
                      >
                        {{
                          !errorPosition.displaySentence ? "Tampilkan Kalimat" : "Sembunyikan Kalimat"
                        }}
                      </button>
                    </td>
                    <td>
                      <select
                          v-model="errorPosition.selectedRecommendation"
                          class="form-control form-control-sm"
                          @change="errorPosition.correction = errorPosition.selectedRecommendation"
                      >
                        <option
                            v-for="(recommendation, recIndex) in errorPosition.recommendations"
                            :key="recIndex"
                            :value="recommendation.word"
                        >
                          {{ recommendation.word }} ({{ recommendation.points }})
                        </option>
                      </select>
                    </td>
                    <td>
                      <label :for="`input_correction_${tokenString}-${index}`"
                             class="sr-only"
                      >
                        Correction
                      </label>
                      <input
                          :id="`input_correction_${tokenString}-${index}`"
                          v-model="errorPosition.correction"
                          class="form-control form-control-sm"
                          type="text"
                      >
                    </td>
                  </tr>
                  <tr v-if="errorPosition.displaySentence">
                    <td colspan="3"
                        v-html="sentenceHtml(tokenString, errorPosition)"
                    >
                    </td>
                  </tr>
                </template>
              </template>
              </tbody>
            </table>
          </div>

          <div
              v-if="Object.keys(this.mTokensWithError).length === 0"
              class="alert alert-success"
          >
            Tidak terdapat kesalahan pengejaan sama sekali.
          </div>
        </div>

        <div
            v-if="Object.keys(this.mTokensWithError).length > 0"
            class="card-footer d-flex justify-content-end"
        >
          <button class="btn btn-primary">
            Perbarui
          </button>
        </div>
      </form>

      <editor
          ref="vue_editor"
          v-model="dokumen.konten_html"
          :disabled="true"
          :init="{
                    menubar: false,
            content_style: '.has-spelling-error{text-decoration: underline;text-decoration-color: red;} .has-highlight { background-color: yellow; }',
         height: 640,
         plugins: [
           'advlist autolink lists link image charmap print preview anchor',
           'searchreplace visualblocks code fullscreen',
           'insertdatetime media table paste code help wordcount',
         ],
         toolbar: false,
       }"
          api-key="c3lgkroj62ttb5dfwxx5eeyc7cqkvqwjm6yyrpm0x8xypjnt"
      ></editor>
    </div>
    <div v-else>
      Loading...
    </div>
  </div>
</template>

<script>
import axios from "axios"
import editor from "@tinymce/tinymce-vue"
import {escapeRegExp} from "lodash"
import Swal from "sweetalert2";

export default {
  components: {
    editor: editor
  },

  props: {
    dataUrl: String,
    correctorUrl: String,
    tokensWithError: Array,
  },

  mounted() {
    let tokenGroupIndex = 0

    this.tokensWithError.forEach(token => {
      let recommendations = Object.keys(token.recommendations)
          .map(word => ({
            word: word,
            points: token.recommendations[word].toFixed(4)
          }))

      if (!this.mTokensWithError.hasOwnProperty(token.value)) {
        this.$set(this.mTokensWithError, token.value, {
          index: tokenGroupIndex++,
          positions: [],
        })
      }

      let correction = recommendations[0].word
      if (token.raw_value[0].toUpperCase() === token.raw_value[0]) {
        correction = correction[0].toUpperCase() + correction.substr(1)
      }

      if (token.raw_value.toUpperCase() === token.raw_value) {
        correction = correction.toUpperCase()
      }

      this.mTokensWithError[token.value].positions.push({
        index: token.index,
        sentence: token.sentence,
        wordPosInSentence: token.pos_in_sentence,
        recommendations: recommendations,
        selectedRecommendation: recommendations[0].word,
        correction: correction,
        displaySentence: false,
      })
    })

    axios.get(this.dataUrl)
        .then(response => {
          this.dokumen = response.data
        })
  },

  data() {
    return {
      dokumen: null,
      mTokensWithError: {},
      processableTextPieces: [],
      processedTextPiecesCount: 0,
      tokenWithErrorIndexCounter: 0,
    }
  },

  computed: {
    formData() {
      return {
        corrections: Object.keys(this.mTokensWithError)
            .map(token => ({
              original: token,
              replacements: this.mTokensWithError[token]
                  .positions
                  .filter(errorPosition =>
                      (errorPosition.correction !== null) &&
                      (errorPosition.correction.length > 0)
                  )
                  .map(errorPosition => ({
                    index: errorPosition.index,
                    correction: errorPosition.correction,
                  }))
            }))
            .filter(correction => correction.replacements.length > 0)
      }
    }
  },

  methods: {
    onDisplaySentenceButtonClick(errorPosition) {
      errorPosition.displaySentence = !errorPosition.displaySentence
    },

    sentenceHtml(errorToken, errorPosition) {
      let counter = 0
      let wordInSentence = null
      
      let regexPattern = `(?<=[\\s,.:;"'()]|^)${escapeRegExp(errorToken)}(?=[\\s,.:;"'()]|$)`
      let matches = errorPosition.sentence.matchAll(new RegExp(regexPattern, "ugi"))
        for (let match of matches) {
        if (errorPosition.wordPosInSentence === counter) {
          wordInSentence = match
          break
        }
        ++counter
      }

      let paragraph = document.createElement("p")
      paragraph.appendChild(document.createTextNode(
          errorPosition.sentence.slice(0, wordInSentence["index"])
      ))
      let span = document.createElement("span")
      span.classList.add("has-highlight", "has-spelling-error")

      span.appendChild(document.createTextNode(wordInSentence[0]))
      paragraph.appendChild(span)

      paragraph.appendChild(document.createTextNode(
          errorPosition.sentence.slice(wordInSentence["index"] + errorToken.length)
      ))

      return paragraph.outerHTML
    },

    onFormSubmit() {
      Swal.fire({
        title: "Memroses Dokumen",
        text: "Dokumen sedang diproses, harap tunggu.",
        onBeforeOpen(popup) {
          Swal.showLoading()
        },
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false
      })

      axios.post(this.correctorUrl, this.formData)
          .then(response => {
            window.location.reload()
          }).catch(error => {
        Swal.hideLoading()
        Swal.close()


        alert("Gagal merevisi dokumen.")
      })
    },
  }
}
</script>
