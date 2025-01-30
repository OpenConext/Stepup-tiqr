import 'jquery';
import {Component} from './Component';

export class MobileOnlyComponent implements Component {

  private readonly onMobile;

  constructor(private element: JQuery) {
    this.onMobile = "ontouchstart" in document.documentElement;

    if (this.onMobile) {
      this.show();
    } else {
      this.hide();
    }
  }

  public show() {
    if (this.onMobile) {
      this.element.show();
    }
  }

  public hide() {
    this.element.hide();
  }
}
