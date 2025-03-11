/**
 * @file defines InsertAiImgCommand, which is executed when the aiImg
 * toolbar button is pressed.
 */
// cSpell:ignore aiimgediting

import {Command} from 'ckeditor5/src/core';
import FormView from './form';
import {ContextualBalloon, clickOutsideHandler} from 'ckeditor5/src/ui';


export default class InsertAiImgCommand extends Command {
  constructor(editor, config) {
    super(editor);
    this._balloon = this.editor.plugins.get(ContextualBalloon);
    this.formView = this._createFormView();
    this._config = config;
  }

  execute() {
    this._showUI();
  }

  _createFormView() {
    const editor = this.editor;
    const formView = new FormView(editor.locale);

    this.listenTo(formView, 'submit', () => {
      const prompt = formView.promptInputView.fieldView.element.value;
      // @todo Need to have an AJAX indicator while the API waits for a response.
      // @todo add error handling
      jQuery("body").addClass("waiting");
      editor.model.change(writer => {
        fetch(drupalSettings.path.baseUrl + 'api/ai-image/getimage', {
          method: 'POST',
          credentials: 'same-origin',
          body: JSON.stringify({'prompt': prompt, 'options': this._config}),
        })
          .then((response) => {
            if (!response.ok) {
              // make the promise be rejected if we didn't get a 2xx response
              console.log(response.statusText);
              return response.statusText;
            } else {
              return response.json();
            }
          }
        )
          .then((answer) => editor.model.insertContent(
            writer.createElement('imageBlock', {
                src: answer.text,
                'alt': prompt
              }
            )))
          .then(() => this._hideUI()
          )

      });
    });

    // Hide the form view after clicking the "Cancel" button.
    this.listenTo(formView, 'cancel', () => {
      this._hideUI();
    });

    // Hide the form view when clicking outside the balloon.
    clickOutsideHandler({
      emitter: formView,
      activator: () => this._balloon.visibleView === formView,
      contextElements: [this._balloon.view.element],
      callback: () => this._hideUI()
    });

    return formView;
  }

  _getBalloonPositionData() {
    const view = this.editor.editing.view;
    const viewDocument = view.document;
    let target = null;

    // Set a target position by converting view selection range to DOM.
    target = () => view.domConverter.viewRangeToDom(
      viewDocument.selection.getFirstRange()
    );

    return {
      target
    };
  }

  _showUI() {
    this._balloon.add({
      view: this.formView,
      position: this._getBalloonPositionData()
    });

    this.formView.focus();
  }

  _hideUI() {
    this.formView.promptInputView.fieldView.value = '';
    this.formView.element.reset();
    this._balloon.remove(this.formView);
    this.editor.editing.view.focus();
    jQuery("body").removeClass("waiting");
  }

  _displayLoading() {

  }
}
