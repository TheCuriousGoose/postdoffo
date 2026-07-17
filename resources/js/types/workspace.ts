export type HttpMethod =
    'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'HEAD' | 'OPTIONS';

export type BodyType = 'none' | 'raw' | 'json' | 'form_data' | 'urlencoded';

export type WorkspaceRole = 'owner' | 'editor' | 'viewer';

export type KeyValuePair = {
    key: string;
    value: string;
    enabled?: boolean;
};

export type RequestBody = {
    raw?: string;
    json?: unknown;
    fields?: KeyValuePair[];
} | null;

export type ApiRequest = {
    id: number;
    collection_id: number;
    name: string;
    method: HttpMethod;
    url: string;
    order: number;
    headers: KeyValuePair[] | null;
    query_params: KeyValuePair[] | null;
    body: RequestBody;
    body_type: BodyType;
    pre_request_script: string | null;
    test_script: string | null;
    created_at: string;
    updated_at: string;
};

export type CollectionNode = {
    id: number;
    name: string;
    parent_id: number | null;
    order: number;
    variables: Record<string, string> | null;
    requests: ApiRequest[];
    children: CollectionNode[];
};

export type EnvironmentVariable = {
    id: number;
    environment_id: number;
    key: string;
    value: string | null;
    is_secret: boolean;
};

export type Environment = {
    id: number;
    workspace_id: number;
    name: string;
    is_active: boolean;
    variables: EnvironmentVariable[];
};

export type Workspace = {
    id: number;
    name: string;
    owner_id: number;
    collections_count?: number;
};

export type TestResult = {
    name: string;
    passed: boolean;
    message: string | null;
};

export type ExecutedResponse = {
    status: number | null;
    headers: Record<string, string[]>;
    body: string | null;
    duration_ms: number;
    test_results: TestResult[];
    error: string | null;
    ok: boolean;
};

export type RequestHistoryEntry = {
    id: number;
    request_id: number | null;
    workspace_id: number;
    user_id: number | null;
    method: string;
    url: string;
    status_code: number | null;
    duration_ms: number | null;
    response_snapshot: ExecutedResponse | null;
    executed_at: string;
};
