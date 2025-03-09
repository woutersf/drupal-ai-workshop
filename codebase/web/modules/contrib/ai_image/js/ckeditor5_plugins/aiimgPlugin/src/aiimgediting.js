import {Plugin} from 'ckeditor5/src/core';
import {Widget} from 'ckeditor5/src/widget';
import InsertAiImgCommand from './insertaiimgcommand';

// cSpell:ignore aiimg insertaiimgcommand

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * This file has the logic for defining the aiImg model, and for how it is
 * converted to standard DOM markup.
 */
export default class AiImgEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    const config = this.editor.config.get('ai_image_aiimg');

    this.editor.commands.add(
        'insertAiImg',
        new InsertAiImgCommand(this.editor, config.aiimage),
    );
  }


}
