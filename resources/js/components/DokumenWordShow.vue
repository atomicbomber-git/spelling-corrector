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
              v-if="Object.keys(this.tokenWithErrors).length > 0"
              style="height: 200px; overflow-y: scroll"
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

              <template v-for="(tokenWithError, tokenString) in tokenWithErrors">
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
                          @click="errorPosition.displaySentence = !errorPosition.displaySentence"
                      >
                        {{ !errorPosition.displaySentence ? "Tampilkan Kalimat" : "Sembunyikan Kalimat" }}
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
              v-if="Object.keys(this.tokenWithErrors).length === 0"
              class="alert alert-success"
          >
            Tidak terdapat kesalahan pengejaan sama sekali.
          </div>

          <!-- Progress bar -->
          <div v-if="this.textProcessingProgress < 100"
               class="my-2"
          >
            <p class="h5">
              Memroses teks untuk memperoleh rekomendasi koreksi ({{ this.textProcessingProgress }}%)
            </p>

            <div class="progress">
              <div :aria-valuenow="`${this.textProcessingProgress}%`"
                   :style="{width: `${this.textProcessingProgress}%`}"
                   aria-valuemax="100"
                   aria-valuemin="0"
                   class="progress-bar"
                   role="progressbar"
              > Processing...
              </div>
            </div>
          </div>
        </div>

        <div
            v-if="Object.keys(this.tokenWithErrors).length > 0"
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
          @onInit="onEditorInit"
      ></editor>
    </div>
    <div v-else>
      Loading...
    </div>
  </div>
</template>

<script>
import axios from "axios"
import tinymce from "tinymce"
import editor from "@tinymce/tinymce-vue"
import {chunk, uniqBy} from "lodash"
import Swal from "sweetalert2";

export default {
  components: {
    editor: editor
  },

  props: {
    dataUrl: String,
    recommenderUrl: String,
    correctorUrl: String,
    corrections: Array,
  },

  mounted() {
    let indexCounters = {}

    uniqBy(this.corrections, (correction) => correction.word)
        .map(correction => correction.word)
        .forEach(word => {
          indexCounters[word] = 0

          this.$set(this.tokenWithErrors, word, {
            index: this.tokenWithErrorIndexCounter++,
            positions: [],
          })
        })

    this.corrections.forEach(correction => {
      let recommendations = Object
          .keys(correction.recommendations)
          .map(word => ({
            word: word,
            points: correction.recommendations[word]
          }))

      this.tokenWithErrors[correction.word].positions.push({
        index: indexCounters[correction.word]++,

        sentence: correction.sentence,
        wordPosInSentence: correction.pos_in_sentence,
        recommendations: recommendations,
        selectedRecommendation: recommendations[0].word,
        correction: recommendations[0].word,
        // selectedRecommendation: null,
        // correction: null,
        displaySentence: false,
      })
    })

    // let errorPosition = {
    //   index: indexCounters[tokenString],
    //   selectedRecommendation: this.tokenWithErrors[tokenString].recommendations[0].word,
    //   correction: this.tokenWithErrors[tokenString].recommendations[0].word,
    //   displaySentence: false,
    //   node: node,
    // }
    //
    // this.tokenWithErrors[tokenString].positions.push(errorPosition)


    axios.get(this.dataUrl)
        .then(response => {
          this.dokumen = response.data
        })
  },

  data() {
    return {
      dokumen: null,
      tokenWithErrors: {},
      processableTextPieces: [],
      processedTextPiecesCount: 0,
      tokenWithErrorIndexCounter: 0,
    }
  },

  computed: {
    textProcessingProgress() {
      return Math.round(this.processedTextPiecesCount / this.processableTextPieces.length * 100)
    },

    formData() {
      return {
        corrections: Object.keys(this.tokenWithErrors)
            .map(token => ({
              original: token,
              replacements: this.tokenWithErrors[token]
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
    sentenceHtml(errorToken, errorPosition) {
      let counter = 0
      let wordInSentence = null
      let matches = errorPosition.sentence.matchAll(new RegExp(`\\b${errorToken}\\b`, "gi"))

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

    symbolPercentage(text) {
      let filtered = text
      filtered = filtered
          .replaceAll(/[^\w]+/g, '')
          .replaceAll(/\d+/g, '')

      return 1 - (filtered.replaceAll(/[^\w]+/g, '').length / text.length)
    },

    jumpIntoText(tokenIndex, positionIndex) {
      let body = this.$refs.vue_editor.editor.getBody()
      let element = body
          .querySelector(`.has-spelling-error-${tokenIndex}-${positionIndex}`)

      element.scrollIntoView(false)

      for (const elem of body.querySelectorAll(".has-spelling-error")) {
        elem.classList.remove("has-highlight")
      }

      element.classList.add("has-highlight")
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

    markTokensThatHasSpellingErrorMultiple: function (tokenStrings) {
      const markerClass = "has-spelling-error"
      let editor = this.$refs.vue_editor.editor
      let editorBody = editor.getBody()
      let replacementList = []

      let indexCounters = {}
      for (let tokenString of tokenStrings) {
        indexCounters[tokenString] = 0
      }

      this.walkNodeTree(editorBody, (node) => {
        if (node.nodeType === Node.TEXT_NODE) {
          let text = node.textContent

          let matches = []
          tokenStrings.forEach(tokenString => {
            let regExp = RegExp(`\\b${tokenString}\\b`, "gi")

            for (let regExpMatchArray of text.matchAll(regExp)) {

              let errorPosition = {
                index: indexCounters[tokenString],
                selectedRecommendation: this.tokenWithErrors[tokenString].recommendations[0].word,
                correction: this.tokenWithErrors[tokenString].recommendations[0].word,
                displaySentence: false,
                node: node,
              }

              this.tokenWithErrors[tokenString].positions.push(errorPosition)

              matches.push({
                errorPosition: errorPosition,
                regexMatchArray: regExpMatchArray,
                index: indexCounters[tokenString],
                tokenIndex: this.tokenWithErrors[tokenString].index
              })

              ++indexCounters[tokenString]
            }
          })

          /* Sort matches by index */
          matches = matches.sort((matchA, matchB) => matchA.regexMatchArray.index - matchB.regexMatchArray.index)

          let prevTextPos = 0
          let documentFragment = document.createDocumentFragment()

          matches.forEach(match => {
            documentFragment.appendChild(
                document.createTextNode(text.slice(prevTextPos, match.regexMatchArray.index))
            )

            /* Spelling error */
            let spellingErrorSpanNode = document.createElement('span')
            spellingErrorSpanNode.appendChild(document.createTextNode(match.regexMatchArray[0]))
            spellingErrorSpanNode.classList.add(`${markerClass}`)
            spellingErrorSpanNode.classList.add(`${markerClass}-${match.tokenIndex}-${match.index}`)
            documentFragment.appendChild(spellingErrorSpanNode)

            prevTextPos = match.regexMatchArray.index + match.regexMatchArray[0].length


            match.errorPosition.node = spellingErrorSpanNode
          })

          documentFragment.appendChild(
              document.createTextNode(text.slice(prevTextPos))
          )


          replacementList.push({
            original: node,
            replacement: documentFragment,
            matches: matches,
          })
        }
      })

      chunk(replacementList, 100)
          .forEach(replacementListChunk => {
            replacementListChunk.forEach(pair => {
              pair.original.parentNode.replaceChild(
                  pair.replacement,
                  pair.original,
              )

              pair.matches.forEach(match => {
                let node = match.errorPosition.node

                node.classList.add('has-highlight')

                let root = node.parentNode

                while (["STRONG", "EM"].includes(root.nodeName)) {
                  root = root.parentNode
                }


                let wrapper = document.createElement("p")
                wrapper.appendChild(root.cloneNode(true))


                node.classList.remove('has-highlight')

                match.errorPosition.node = wrapper
              })
            })
          })
    },

    walkNodeTree(node, callback) {
      node.childNodes.forEach(childNode => {
        callback(childNode)
        this.walkNodeTree(childNode, callback)
      })
    },

    getProcessableTextPieces: function (editor) {
      let processableTextPieces = []
      let walker = new tinymce.dom.TreeWalker(editor.dom.getRoot());

      do {
        let node = walker.current()
        if (
            node.nodeType !== Node.TEXT_NODE ||
            node.parentNode.classList.contains("has-spelling-error")
        ) {
          continue
        }

        let parts = node.textContent.split(/\b/)

        parts.forEach(part => {
          processableTextPieces.push(part)
        })
      } while (walker.next());


      processableTextPieces = processableTextPieces
          .filter(textPiece => textPiece.length > 1)
          .filter(textPiece => this.symbolPercentage(textPiece) < 0.1)
          .map(textPiece => textPiece
              .replace(new RegExp("[^\\w]*$", "gm"), '')
              .replace(new RegExp("^[^\\w]*", "gm"), '')
          )

      processableTextPieces = uniqBy(processableTextPieces, textPiece => textPiece.toLowerCase())
      processableTextPieces = chunk(processableTextPieces, 100)

      return processableTextPieces
    },

    onEditorInit(e) {
      // let editor = e.target
      // this.processableTextPieces = this.getProcessableTextPieces(editor)
      // this.fetchRecommendationsFromServer()
    },

    getSpellingRecommendations(tokens) {
      return axios.post(this.recommenderUrl, {
        tokens: tokens
      })
    },

    async fetchRecommendationsFromServer() {
      for (const textTokens of this.processableTextPieces) {
        const tokens = textTokens
            .filter(token => !this.tokenWithErrors.hasOwnProperty(token.toLowerCase()))

        const recommendationData = await this.getSpellingRecommendations(tokens)

        recommendationData.data.forEach(recommendationDatum => {
          if (this.tokenWithErrors.hasOwnProperty(recommendationDatum.token)) {
            return;
          }

          this.$set(this.tokenWithErrors, recommendationDatum.token, {
            index: this.tokenWithErrorIndexCounter++,
            positions: [],
            recommendations: recommendationDatum.recommendations
          })
        })
        ++this.processedTextPiecesCount
      }

      this.markTokensThatHasSpellingErrorMultiple(Object.keys(this.tokenWithErrors))
    },
  }
}
</script>
