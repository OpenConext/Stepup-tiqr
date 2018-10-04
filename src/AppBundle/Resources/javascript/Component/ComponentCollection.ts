import { Component } from './Component';

export class ComponentCollection implements Component {

  constructor(private components: Component[]) {

  }

  public hide() {
    for (const component of this.components) {
      component.hide();
    }
  }

  public show() {
    for (const component of this.components) {
      component.show();
    }
  }
}
