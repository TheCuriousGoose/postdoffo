import type { HttpMethod } from '@/types/workspace';

/**
 * One method palette for the whole app. The tab strip, the collection tree, the
 * command palette and the method picker each carried their own copy of this map,
 * which is how they drift: change a colour in one and three other surfaces keep
 * the old one.
 */
export const methodColor: Record<string, string> = {
    GET: 'text-blue-600 dark:text-blue-400',
    POST: 'text-green-600 dark:text-green-400',
    PUT: 'text-amber-600 dark:text-amber-400',
    PATCH: 'text-violet-600 dark:text-violet-400',
    DELETE: 'text-red-600 dark:text-red-400',
    HEAD: 'text-muted-foreground',
    OPTIONS: 'text-muted-foreground',
};

export const httpMethods: HttpMethod[] = [
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'HEAD',
    'OPTIONS',
];
