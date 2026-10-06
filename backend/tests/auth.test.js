const request = require("supertest");
const app = require("../src/server");

let testEmail;
let duplicateEmail;

describe("VEYRA Authentication API", () => {

    test("Registration should reject missing fields", async () => {

        const response = await request(app)
            .post("/api/auth/register")
            .send({});

        expect(response.statusCode).toBe(400);

        expect(response.body.success).toBe(false);

        expect(response.body.message).toBe(
            "Name, email and password are required."
        );
    });


    test("Registration should reject invalid email", async () => {

        const response = await request(app)
            .post("/api/auth/register")
            .send({
                name: "Test User",
                email: "invalid-email",
                password: "Password123!"
            });

        expect(response.statusCode).toBe(400);

        expect(response.body.success).toBe(false);

        expect(response.body.message).toBe(
            "Please provide a valid email address."
        );
    });


    test("Registration should reject short passwords", async () => {

        const response = await request(app)
            .post("/api/auth/register")
            .send({
                name: "Test User",
                email: "test@example.com",
                password: "123"
            });

        expect(response.statusCode).toBe(400);

        expect(response.body.success).toBe(false);

        expect(response.body.message).toBe(
            "Password must be at least 8 characters."
        );
    });


    test("Registration should reject very short names", async () => {

        const response = await request(app)
            .post("/api/auth/register")
            .send({
                name: "A",
                email: "test@example.com",
                password: "Password123!"
            });

        expect(response.statusCode).toBe(400);

        expect(response.body.success).toBe(false);

        expect(response.body.message).toBe(
            "Name must contain at least 2 characters."
        );
    });


    test("Registration should create a new user successfully", async () => {

        testEmail =
            `test-${Date.now()}@veyra-test.com`;

        const response = await request(app)
            .post("/api/auth/register")
            .send({
                name: "VEYRA Test User",
                email: testEmail,
                password: "Password123!"
            });

        expect(response.statusCode).toBe(201);

        expect(response.body.success).toBe(true);

        expect(response.body.message).toBe(
            "Account created successfully."
        );

        expect(response.body.user).toHaveProperty("id");

        expect(response.body.user.name).toBe(
            "VEYRA Test User"
        );

        expect(response.body.user.email).toBe(
            testEmail
        );

        expect(response.body.user.role).toBe("user");
    });


    test("Registration should reject duplicate email", async () => {

        duplicateEmail =
            `duplicate-${Date.now()}@veyra-test.com`;


        // Create the first account
        const firstResponse = await request(app)
            .post("/api/auth/register")
            .send({
                name: "First User",
                email: duplicateEmail,
                password: "Password123!"
            });

        expect(firstResponse.statusCode).toBe(201);


        // Try to create another account
        // using the same email
        const secondResponse = await request(app)
            .post("/api/auth/register")
            .send({
                name: "Second User",
                email: duplicateEmail,
                password: "Password123!"
            });

        expect(secondResponse.statusCode).toBe(409);

        expect(secondResponse.body.success).toBe(false);

        expect(secondResponse.body.message).toBe(
            "An account with this email already exists."
        );
    });

});


afterAll(async () => {

    const db = require("../src/db");


    // Remove successful registration test user
    if (testEmail) {

        await db.query(
            "DELETE FROM users WHERE email = ?",
            [testEmail]
        );

    }


    // Remove duplicate-email test user
    if (duplicateEmail) {

        await db.query(
            "DELETE FROM users WHERE email = ?",
            [duplicateEmail]
        );

    }


    // Close database connection pool
    await db.end();

});