# syntax=docker/dockerfile:1@sha256:87999aa3d42bdc6bea60565083ee17e86d1f3339802f543c0d03998580f9cb89

FROM golang:1.26-bookworm@sha256:1ecb7edf62a0408027bd5729dfd6b1b8766e578e8df93995b225dfd0944eb651 AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" \
    -o /out/ruwordnetview ./cmd/ruwordnetview

FROM gcr.io/distroless/static-debian12:nonroot@sha256:aef9602f8710ec12bde19d593fed1f76c708531bb7aba205110f1029786ead7b
WORKDIR /app
COPY --from=build /out/ruwordnetview ./ruwordnetview
COPY --from=build /src/views ./views
COPY --from=build /src/app/locales ./app/locales
COPY --from=build /src/web ./web
USER nonroot:nonroot
EXPOSE 8000
ENTRYPOINT ["/app/ruwordnetview"]
