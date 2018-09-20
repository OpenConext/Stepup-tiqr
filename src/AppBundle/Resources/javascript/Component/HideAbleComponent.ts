import 'jquery';
import { Component } from './Component';

export class HideAbleComponent implements Component {

  constructor(private element: JQuery) {

  }

  public show() {
    this.element.show();
  }

  public hide() {
    this.element.hide();
  }
}
