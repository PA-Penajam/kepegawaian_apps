import ErrorPage from './error';

export default function ForbiddenPage({ message }: { message?: string }) {
    return <ErrorPage status={403} message={message} />;
}
