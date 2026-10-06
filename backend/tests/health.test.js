const request = require("supertest");
const app = require("../src/server.js");

describe("VEYRA API Health", () => {

    test("GET /api should return API information", async () => {
        const response = await request(app)
            .get("/api");

        expect(response.statusCode).toBe(200);

        expect(response.body).toEqual({
            name: "VEYRA API",
            version: "1.0.0",
            status: "online"
        });
    });

});
    test("GET /api/health should confirm database connection", async () => {
        const response = await request(app)
            .get("/api/health");

        expect(response.statusCode).toBe(200);

        expect(response.body.success).toBe(true);
        expect(response.body.database).toBe(true);
    });
        test("GET /api/auth/status should confirm authentication API", async () => {
        const response = await request(app)
            .get("/api/auth/status");

        expect(response.statusCode).toBe(200);

        expect(response.body.success).toBe(true);
        expect(response.body.message).toBe(
            "Authentication API is working."
        );
    });
        test("Unknown endpoint should return 404", async () => {
        const response = await request(app)
            .get("/api/does-not-exist");

        expect(response.statusCode).toBe(404);

        expect(response.body.success).toBe(false);
        expect(response.body.message).toBe(
            "API endpoint not found."
        );
    });
    afterAll(async () => {
    const db = require("../src/db");
    await db.end();
});