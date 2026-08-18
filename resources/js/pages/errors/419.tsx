import ErrorPage from './error';

export default function PageExpiredPage({ message }: { message?: string }) {
    return <ErrorPage status={419} message={message} />;
}
