import 'jquery';
import { Component } from './Component';

export class SlideableComponent implements Component {

  constructor(private element: JQuery) {

  }

  public show() {
    this.element.stop();
    this.element.slideDown(() => {
      /**
       * jQuery leaves attributes, remove those that where left behind.
       */
      this.element.removeAttr('style');
    });
  }

  public hide() {
    this.element.stop();
    this.element.slideUp();
  }
}
