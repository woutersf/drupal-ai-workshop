import {
  View,
  LabeledFieldView,
  createLabeledInputText,
  ButtonView,
  submitHandler
} from 'ckeditor5/src/ui';
import {icons} from 'ckeditor5/src/core';

export default class FormView extends View {
  constructor(locale) {
    super(locale);

    this.promptInputView = this._createInput('Generate image for this prompt:');

    this.saveButtonView = this._createButton(
        'Submit', icons.check, 'ck-button-save'
    );

    this.saveButtonView.type = 'submit';

    this.cancelButtonView = this._createButton(
        'Cancel', icons.cancel, 'ck-button-cancel'
    );

    this.cancelButtonView.delegate('execute').to(this, 'cancel');

    this.childViews = this.createCollection(
        [
          this.promptInputView,
          this.saveButtonView,
          this.cancelButtonView
        ]
    );

    this.setTemplate(
        {
          tag: 'form',
          attributes: {
            class: ['ck', 'ck-openai-completion-form', 'ck-openai-form'],
            tabindex: '-1'
          },
          children: this.childViews
        }
    );
  }

  _createInput(label) {
    const labeledInput = new LabeledFieldView(this.locale, createLabeledInputText);
    labeledInput.label = label;
    return labeledInput;
  }

  _createButton(label, icon, className) {
    const button = new ButtonView();

    button.set(
        {
          label,
          icon,
          tooltip: true,
          class: className
        }
    );

    return button;
  }

  render() {
    super.render();

    // Submit the form when the user clicked the save button
    // or pressed enter in the input.
    submitHandler(
        {
          view: this
        }
    );
  }

  focus() {
    this.childViews.first.focus();
  }

}
