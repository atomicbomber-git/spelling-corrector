<template>
  <div>
    <div v-if="dokumen">

      <div class="card mb-3">
        <div class="card-body">
          <div class="my-2" v-if="this.textProcessingProgress < 100">
            <p class="h5">
              Memroses teks untuk memperoleh rekomendasi koreksi ({{ this.textProcessingProgress }}%)
            </p>

            <div class="progress">
              <div class="progress-bar"
                   role="progressbar"
                   :style="{width: `${this.textProcessingProgress}%`}"
                   :aria-valuenow="`${this.textProcessingProgress}%`"
                   aria-valuemin="0"
                   aria-valuemax="100"
              > Processing...
              </div>
            </div>
          </div>
        </div>
      </div>

      <editor
          ref="vue_editor"
          v-model="dokumen.konten_html"
          :init="{
            content_style: '.has-spelling-error{text-decoration: underline;text-decoration-color: red;}',
         height: 640,
         menubar: true,
         plugins: [
           'advlist autolink lists link image charmap print preview anchor',
           'searchreplace visualblocks code fullscreen',
           'insertdatetime media table paste code help wordcount',
           'textcolor',
         ],
         toolbar:
           'undo redo | formatselect | bold italic forecolor backcolor | \
           alignleft aligncenter alignright alignjustify | \
           bullist numlist outdent indent | removeformat | help'
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
import {chunk} from "lodash"

export default {
  components: {
    editor: editor
  },

  props: {
    dataUrl: String,
    recommenderUrl: String,
  },

  mounted() {
    axios.get(this.dataUrl)
        .then(response => {
          this.dokumen = response.data
        })
  },

  data() {
    return {
      dokumen: null,
      recommendations: {},
      processableTextPieces: [],
      processedTextPiecesCount: 0,
    }
  },

  computed: {
      textProcessingProgress() {
        return Math.round(this.processedTextPiecesCount / this.processableTextPieces.length * 100)
      }
  },

  methods: {
    markTokensThatHasSpellingError: function (editor, token) {
      console.log(token)

      const markerClass = "has-spelling-error"
      let editorContent = editor.getContent()

      let tokenPos = editorContent.toLowerCase().indexOf(token.toLowerCase())
      while (tokenPos !== -1) {
        let tokenAsInContent = editorContent.slice(tokenPos, tokenPos + token.length)
        let replacement = `<span class="${markerClass}"> ${tokenAsInContent} </span>`
        let contentLowerHalf = editorContent.slice(0, tokenPos)
        let contentUpperHalf = editorContent.slice(tokenPos + token.length)
        let newEditorContent = contentLowerHalf + replacement + contentUpperHalf
        editor.setContent(newEditorContent)
        editorContent = newEditorContent
        tokenPos = editorContent.toLowerCase().indexOf(
            token.toLowerCase(),
            tokenPos + replacement.length
        )
      }
    },

    getProcessableTextPieces: function (editor) {
      let processableTextPieces = []
      let walker = new tinymce.dom.TreeWalker(editor.dom.getRoot());

      do {
        let node = walker.current()
        if (node.nodeType !== Node.TEXT_NODE) {
          continue
        }
        if (node.parentNode.classList.contains("has-spelling-error")) {
          continue
        }

        let parts = node.textContent.split(' ')
        parts.forEach(part => {
          processableTextPieces.push(part)
        })
      } while (walker.next());

      return processableTextPieces
    },


    onEditorInit(e) {
      let editor = e.target
      // let token = "ALICE"
      // this.markTokensThatHasSpellingError(editor, token);
      this.processableTextPieces = chunk(this.getProcessableTextPieces(editor), 15)
      this.fetchRecommendationsFromServer()
    },

    getSpellingRecommendations(text) {
      return axios.post(this.recommenderUrl, {
        text: text
      })
    },

    async fetchRecommendationsFromServer() {
      for (const textTokens of this.processableTextPieces) {
        const text = textTokens
            .map(token => token
                .replace(new RegExp("[^\\w]*$", "gm"), '')
                .replace(new RegExp("^[^\\w]*", "gm"), '')
            )
            .filter(token => token.length > 1)
            .filter(token => !this.recommendations.hasOwnProperty(token.toLowerCase())
            ).join(' ')

        const recommendationData = await this.getSpellingRecommendations(text)

        recommendationData.data.forEach(recommendationDatum => {
          if (this.recommendations.hasOwnProperty(recommendationDatum.token)) {
            return;
          }

          this.recommendations[recommendationDatum.token] = recommendationDatum.recommendations
          // this.markTokensThatHasSpellingError(
          //     this.$refs.vue_editor.editor,
          //     recommendationDatum.token,
          // )
        })
        ++this.processedTextPiecesCount
      }
    }
  }
}
</script>
