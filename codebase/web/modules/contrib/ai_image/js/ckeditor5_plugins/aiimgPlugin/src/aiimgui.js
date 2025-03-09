/**
 * @file registers the aiImg toolbar button and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/ai-image.svg';
import InsertAiImgCommand from "./insertaiimgcommand";

export default class AiImgUI extends Plugin {
  init() {
    const editor = this.editor;
    const config = this.editor.config.get('ai_image_aiimg');

    editor.commands.add('insertAiImg', new InsertAiImgCommand(this.editor, config.aiimage));

    // This will register the aiImg toolbar button.
    editor.ui.componentFactory.add('aiImg', (locale) => {
      const command = editor.commands.get('insertAiImg');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('Generate Image by AI'),
        icon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () =>
        editor.execute('insertAiImg'),
      );

      return buttonView;
    });
  }
}
