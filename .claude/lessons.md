## 2026-03-15 - Don't add error handling for impossible cases

**Rule:** Only add try-catch blocks at system boundaries (user input,
API calls, file I/O). Don't wrap internal function calls that can't
realistically fail.
**Why:** Added defensive error handling around a pure math function.
User said "this function takes two integers and adds them, it can't
throw. You're adding complexity for nothing."
**Applies when:** Writing or reviewing error handling in any codebase.