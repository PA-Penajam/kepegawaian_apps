import ErrorPage from './error';

export default function ServerErrorPage({ message }: { message?: string }) {
    return <ErrorPage status={500} message={message} />;
}
