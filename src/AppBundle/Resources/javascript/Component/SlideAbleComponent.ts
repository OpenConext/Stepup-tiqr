import 'jquery';
import { Component } from './Component';

export class SlideAbleComponent implements Component {

  constructor(private element: JQuery) {

  }

  public show() {
    this.element.stop();
    this.element.slideDown();
  }

  public hide() {
    this.element.stop();
    this.element.slideUp();
  }
}
