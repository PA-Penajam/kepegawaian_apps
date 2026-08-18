import ErrorPage from './error';

export default function NotFoundPage({ message }: { message?: string }) {
    return <ErrorPage status={404} message={message} />;
}
