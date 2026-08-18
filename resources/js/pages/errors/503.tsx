import ErrorPage from './error';

export default function ServiceUnavailablePage({ message }: { message?: string }) {
    return <ErrorPage status={503} message={message} />;
}
