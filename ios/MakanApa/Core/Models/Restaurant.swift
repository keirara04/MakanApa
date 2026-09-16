import Foundation

struct Restaurant: Codable, Identifiable, Equatable {
    let id: Int
    let name: String
    let latitude: Double
    let longitude: Double
    let address: String?
    let priceLevel: Int?
    let rating: Double?
    let isActive: Bool
    let provider: String?
    let providerPlaceId: String?
    let openingHours: [String: String]?
    let cuisines: [String]
    let tags: [String]
    let signatureDish: String?
}
