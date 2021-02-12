<template>
  <div>
    <div v-if="dokumen">
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
      processedCount: 0,
    }
  },

  methods: {
    markTokensThatHasSpellingError: function (editor, token) {
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

      var walker = new tinymce.dom.TreeWalker(editor.dom.getRoot());

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

      this.obtainRecommendations()
    },

    async obtainRecommendations() {
      let getSpellingRecommendations = text => {
        return axios.post(this.recommenderUrl, {
          text: text
        })
      }

      for (const textTokens of this.processableTextPieces) {
        const text = textTokens
            .map(token => token
                .replace(new RegExp("[^\\w]*$", "gm"), '')
                .replace(new RegExp("^[^\\w]*", "gm"), '')
            )
            .filter(token => token.length > 0)
            .filter(token => !this.recommendations.hasOwnProperty(token.toLowerCase())
        ).join(' ')

        console.log(text)

        const recommendationData = await getSpellingRecommendations(text)

        recommendationData.data.forEach(recommendationDatum => {
          if (this.recommendations.hasOwnProperty(recommendationDatum.token)) {
            return;
          }
          this.recommendations[recommendationDatum.token] = recommendationDatum.recommendations
        })
      }
    }
  }
}
</script>
