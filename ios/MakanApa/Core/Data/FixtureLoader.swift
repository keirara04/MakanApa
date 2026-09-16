import Foundation

enum FixtureLoader {
    enum LoadError: Error {
        case fileNotFound
    }

    static func loadRestaurants(bundle: Bundle = .main) throws -> [Restaurant] {
        guard let url = bundle.url(forResource: "restaurants", withExtension: "json") else {
            throw LoadError.fileNotFound
        }
        let data = try Data(contentsOf: url)
        return try JSONDecoder().decode([Restaurant].self, from: data)
    }
}
