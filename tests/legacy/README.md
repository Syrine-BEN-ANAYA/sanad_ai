Legacy unit scripts written against earlier constructors and hard-coded
database ids. They no longer run as-is; their scenarios (isolation, RBAC,
sessions, documents, transaction workflow) are covered end-to-end by
`tests/api_smoke_test.php`. Kept for reference only.
