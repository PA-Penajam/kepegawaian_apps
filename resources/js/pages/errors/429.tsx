import ErrorPage from './error';

export default function TooManyRequestsPage({ message }: { message?: string }) {
    return <ErrorPage status={429} message={message} />;
}
