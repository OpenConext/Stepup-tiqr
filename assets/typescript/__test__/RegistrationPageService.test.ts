/**
 * @jest-environment jsdom
 */
import 'jest';
import { RegistrationStateMachine } from '../RegistrationStateMachine';

describe('RegistrationPageService', () => {
  let statusCallback: ((status: string) => void) | undefined;
  let errorCallback: ((error: unknown) => void) | undefined;
  let context = createTestContext();

  beforeEach(() => {
    context = createTestContext();
  });

  describe('When starting', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be enabled', () => {
      expect(context.pollingService.enabled).toBeTruthy();
    });

    it('The tiqr app help function should be shown', () => {
      expect(context.statusUi.showOpenTiqrApp).toBeCalled();
    });
  });

  describe('When idle', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.IDLE);
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be enabled', () => {
      expect(context.pollingService.enabled).toBeTruthy();
    });

    it('Show open tiqr app', () => {
      expect(context.statusUi.showOpenTiqrApp).toBeCalled();
    });
  });

  describe('When initialized', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.INITIALIZED);
    });

    it('The qr code should be shown', () => {
      expect(context.qrComponent.isVisible()).toBeTruthy();
    });

    it('Polling should be enabled', () => {
      expect(context.pollingService.enabled).toBeTruthy();
    });

    it('Show open tiqr app', () => {
      expect(context.statusUi.showOpenTiqrApp).toBeCalled();
    });
  });

  describe('When retrieved', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.RETRIEVED);
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be enabled', () => {
      expect(context.pollingService.enabled).toBeTruthy();
    });

    it('Show account activation help', () => {
      expect(context.statusUi.showAccountActivationHelp).toBeCalled();
    });
  });

  describe('When processed', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.PROCESSED);
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be enabled', () => {
      expect(context.pollingService.enabled).toBeTruthy();
    });

    it('Show one moment please', () => {
      expect(context.statusUi.showOneMomentPlease).toBeCalled();
    });
  });

  describe('When finalized', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.FINALIZED);
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.enabled).toBeFalsy();
    });

    it('Show finalized', () => {
      expect(context.statusUi.showFinalized).toBeCalled();
    });
  });

  describe('When timeout', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.TIMEOUT);
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.enabled).toBeFalsy();
    });

    it('Show finalized', () => {
      expect(context.statusUi.showTimeoutHappened).toBeCalled();
    });
  });

  describe('When connection error occurred', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      errorCallback('error');
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.enabled).toBeFalsy();
    });

    it('Show error page', () => {
      expect(context.statusUi.showUnknownErrorHappened).toBeCalled();
    });
  });

  describe('When random status is given', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback('random status');
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.enabled).toBeFalsy();
    });

    it('Show error page', () => {
      expect(context.statusUi.showUnknownErrorHappened).toBeCalled();
    });
  });

  describe('When sessions time out', () => {
    beforeEach(() => {
      context.authenticationPageService.start();
      if (!statusCallback || !errorCallback) {
        throw new Error('Should have started status request');
      }
      statusCallback(RegistrationStateMachine.RETRIEVED);
      statusCallback(RegistrationStateMachine.IDLE);
    });

    it('The qr code should be hidden', () => {
      expect(context.qrComponent.isVisible()).toBeFalsy();
    });

    it('Polling should be disabled', () => {
      expect(context.pollingService.enabled).toBeFalsy();
    });

    it('Show error page', () => {
      expect(context.statusUi.showExpiredSessionStatus).toBeCalled();
    });
  });

  function createTestContext() {
    const pollingService = {
      enabled: false,
      waitAndRequestStatus: jest.fn(((success: any, error: any) => {
        statusCallback = success;
        errorCallback = error;
        (pollingService as any).enabled = true;
      })),
      stop: jest.fn(() => {
        (pollingService as any).enabled = false;
      }),
    };

    const registrationStatusComponent = {
      showExpiredSessionStatus: jest.fn(),
      showOpenTiqrApp: jest.fn(),
      showAccountActivationHelp:jest.fn(),
      showOneMomentPlease: jest.fn(),
      showFinalized: jest.fn(),
      showTimeoutHappened: jest.fn(),
      showUnknownErrorHappened: jest.fn(),
    };

    function createMockComponent() {
      let visible = false;
      return {
        isVisible: () => visible,
        show: () => {
          visible = true;
        },
        hide: () => {
          visible = false;
        },
      };
    }

    const qrComponent = createMockComponent();

    const authenticationPageService = new RegistrationStateMachine(
      pollingService as any,
      registrationStatusComponent as any,
      qrComponent,
      'http://fake-finalized-url.com',
    );
    return {
      pollingService,
      authenticationPageService,
      statusUi: registrationStatusComponent,
      qrComponent,
    };
  }
});
