import { RegistrationStateMachine } from './RegistrationStateMachine';
import { StatusClient } from './Client/StatusClient';
import { StatusPollService } from './StatusPollService';
import { RegistrationStatusComponent } from './Component/RegistrationStatusComponent';
import { SlideableComponent } from './Component/SlideableComponent';
import jQuery from 'jquery';

declare global {
  interface Window {
    bootstrapRegistration: (
      statusApiUrl: string,
      notificationApiUrl: string,
      correlationLoggingId: string
    ) => RegistrationStateMachine;
  }
}

window.bootstrapRegistration = (statusApiUrl: string, finalizedUrl: string, correlationLoggingId: string) => {
  const statusClient = new StatusClient(statusApiUrl, correlationLoggingId);
  const pollingService = new StatusPollService(statusClient);
  const machine = new RegistrationStateMachine(
    pollingService,
    new RegistrationStatusComponent(),
    new SlideableComponent(jQuery('.qr')),
    finalizedUrl,
  );
  machine.start();
  return machine;
};
